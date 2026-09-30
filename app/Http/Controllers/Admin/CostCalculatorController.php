<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCostSummary;
use App\Models\ProductExtraCostItem;
use App\Models\ProductMaterialCostItem;
use App\Models\RawMaterial;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CostCalculatorController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->select(['id', 'name', 'base_price', 'sale_price', 'cost_price'])
            ->latest('id')
            ->get();

        $materials = RawMaterial::query()
            ->latest('id')
            ->get();

        $selectedProduct = null;
        $materialItems = collect();
        $extraItems = collect();
        $summary = null;

        if ($request->filled('product_id')) {
            $selectedProduct = Product::find($request->integer('product_id'));

            if ($selectedProduct) {
                $materialItems = ProductMaterialCostItem::where('product_id', $selectedProduct->id)->get();
                $extraItems = ProductExtraCostItem::where('product_id', $selectedProduct->id)->get();
                $summary = ProductCostSummary::where('product_id', $selectedProduct->id)->first();
            }
        }

        $totals = [
            'materials_count' => $materials->count(),
            'products_with_cost' => ProductCostSummary::count(),
            'total_cost_value' => ProductCostSummary::sum('total_cost'),
            'total_profit_value' => ProductCostSummary::sum('profit'),
        ];

        return view('admin.cost-calculator.index', compact(
            'products',
            'materials',
            'selectedProduct',
            'materialItems',
            'extraItems',
            'summary',
            'totals'
        ));
    }

    public function storeMaterial(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'code' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        RawMaterial::create($data);

        return back()->with('success', __('Raw material added successfully.'));
    }

    public function destroyMaterial(RawMaterial $material)
    {
        $material->delete();

        return back()->with('success', __('Raw material deleted successfully.'));
    }

    public function saveProductCost(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'selling_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'materials' => ['nullable', 'array'],
            'materials.*.raw_material_id' => ['nullable', 'exists:raw_materials,id'],
            'materials.*.material_name' => ['nullable', 'string', 'max:190'],
            'materials.*.unit' => ['nullable', 'string', 'max:50'],
            'materials.*.quantity' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'extras' => ['nullable', 'array'],
            'extras.*.name' => ['nullable', 'string', 'max:190'],
            'extras.*.amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        DB::transaction(function () use ($data) {
            $productId = (int) $data['product_id'];

            ProductMaterialCostItem::where('product_id', $productId)->delete();
            ProductExtraCostItem::where('product_id', $productId)->delete();

            $materialsCost = BigDecimal::of('0.00');

            foreach (($data['materials'] ?? []) as $index => $row) {
                $quantity = BigDecimal::of((string) ($row['quantity'] ?? '0'))
                    ->toScale(3, RoundingMode::Unnecessary);
                $unitPrice = BigDecimal::of((string) ($row['unit_price'] ?? '0'))
                    ->toScale(2, RoundingMode::Unnecessary);

                if ($quantity->compareTo('0') <= 0 || $unitPrice->compareTo('0') < 0) {
                    continue;
                }

                $rawMaterial = null;

                if (! empty($row['raw_material_id'])) {
                    $rawMaterial = RawMaterial::find($row['raw_material_id']);
                }

                $materialName = $rawMaterial?->name ?: ($row['material_name'] ?? null);

                if (! $materialName) {
                    continue;
                }

                $lineCost = $quantity
                    ->multipliedBy($unitPrice)
                    ->toScale(2, RoundingMode::HalfUp);
                $this->assertMoneyRange($lineCost, "materials.{$index}.unit_price");

                $materialsCost = $materialsCost->plus($lineCost);
                $this->assertMoneyRange($materialsCost, 'materials');

                ProductMaterialCostItem::create([
                    'product_id' => $productId,
                    'raw_material_id' => $rawMaterial?->id,
                    'material_name' => $materialName,
                    'unit' => $rawMaterial?->unit ?: ($row['unit'] ?? null),
                    'quantity' => (string) $quantity,
                    'unit_price' => (string) $unitPrice,
                    'total_cost' => (string) $lineCost,
                ]);
            }

            $extraCost = BigDecimal::of('0.00');

            foreach (($data['extras'] ?? []) as $row) {
                $name = $row['name'] ?? null;
                $amount = BigDecimal::of((string) ($row['amount'] ?? '0'))
                    ->toScale(2, RoundingMode::Unnecessary);

                if (! $name || $amount->compareTo('0') <= 0) {
                    continue;
                }

                $extraCost = $extraCost->plus($amount);
                $this->assertMoneyRange($extraCost, 'extras');

                ProductExtraCostItem::create([
                    'product_id' => $productId,
                    'name' => $name,
                    'amount' => (string) $amount,
                ]);
            }

            $sellingPrice = BigDecimal::of((string) $data['selling_price'])
                ->toScale(2, RoundingMode::Unnecessary);
            $totalCost = $materialsCost
                ->plus($extraCost)
                ->toScale(2, RoundingMode::Unnecessary);
            $this->assertMoneyRange($totalCost, 'extras');

            $profit = $sellingPrice
                ->minus($totalCost)
                ->toScale(2, RoundingMode::Unnecessary);
            $this->assertMoneyRange($profit, 'selling_price');

            // Profit margin is profit as a percentage of selling price.
            // (profit / cost) would be markup, which is a different metric.
            $profitMargin = $sellingPrice->compareTo('0') > 0
                ? $profit->multipliedBy('100')->dividedBy($sellingPrice, 2, RoundingMode::HalfUp)
                : BigDecimal::of('0.00');
            $this->assertProfitMarginRange($profitMargin);

            ProductCostSummary::updateOrCreate(
                ['product_id' => $productId],
                [
                    'materials_cost' => (string) $materialsCost,
                    'extra_cost' => (string) $extraCost,
                    'total_cost' => (string) $totalCost,
                    'selling_price' => (string) $sellingPrice,
                    'profit' => (string) $profit,
                    'profit_margin' => (string) $profitMargin,
                ]
            );

            Product::whereKey($productId)->update([
                'cost_price' => (string) $totalCost,
            ]);
        });

        return redirect()
            ->route('admin.cost-calculator.index', ['product_id' => $data['product_id']])
            ->with('success', __('Product cost saved successfully.'));
    }

    private function assertMoneyRange(BigDecimal $amount, string $field): void
    {
        if ($amount->compareTo('-9999999999.99') < 0 || $amount->compareTo('9999999999.99') > 0) {
            throw ValidationException::withMessages([
                $field => __('The calculated amount exceeds the supported monetary range.'),
            ]);
        }
    }

    private function assertProfitMarginRange(BigDecimal $margin): void
    {
        if ($margin->compareTo('-999999.99') < 0 || $margin->compareTo('999999.99') > 0) {
            throw ValidationException::withMessages([
                'selling_price' => __('The resulting profit margin exceeds the supported range.'),
            ]);
        }
    }
}
