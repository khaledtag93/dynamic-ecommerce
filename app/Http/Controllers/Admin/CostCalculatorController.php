<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCostSummary;
use App\Models\ProductExtraCostItem;
use App\Models\ProductMaterialCostItem;
use App\Models\RawMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'unit_price' => ['required', 'numeric', 'min:0'],
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
            'selling_price' => ['required', 'numeric', 'min:0'],
            'materials' => ['nullable', 'array'],
            'materials.*.raw_material_id' => ['nullable', 'exists:raw_materials,id'],
            'materials.*.material_name' => ['nullable', 'string', 'max:190'],
            'materials.*.unit' => ['nullable', 'string', 'max:50'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'materials.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'extras' => ['nullable', 'array'],
            'extras.*.name' => ['nullable', 'string', 'max:190'],
            'extras.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            $productId = (int) $data['product_id'];

            ProductMaterialCostItem::where('product_id', $productId)->delete();
            ProductExtraCostItem::where('product_id', $productId)->delete();

            $materialsCost = 0;

            foreach (($data['materials'] ?? []) as $row) {
                $quantity = (float) ($row['quantity'] ?? 0);
                $unitPrice = (float) ($row['unit_price'] ?? 0);

                if ($quantity <= 0 || $unitPrice < 0) {
                    continue;
                }

                $rawMaterial = null;

                if (!empty($row['raw_material_id'])) {
                    $rawMaterial = RawMaterial::find($row['raw_material_id']);
                }

                $materialName = $rawMaterial?->name ?: ($row['material_name'] ?? null);

                if (!$materialName) {
                    continue;
                }

                $totalCost = round($quantity * $unitPrice, 2);
                $materialsCost += $totalCost;

                ProductMaterialCostItem::create([
                    'product_id' => $productId,
                    'raw_material_id' => $rawMaterial?->id,
                    'material_name' => $materialName,
                    'unit' => $rawMaterial?->unit ?: ($row['unit'] ?? null),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_cost' => $totalCost,
                ]);
            }

            $extraCost = 0;

            foreach (($data['extras'] ?? []) as $row) {
                $name = $row['name'] ?? null;
                $amount = (float) ($row['amount'] ?? 0);

                if (!$name || $amount <= 0) {
                    continue;
                }

                $extraCost += $amount;

                ProductExtraCostItem::create([
                    'product_id' => $productId,
                    'name' => $name,
                    'amount' => $amount,
                ]);
            }

            $sellingPrice = (float) $data['selling_price'];
            $totalCost = round($materialsCost + $extraCost, 2);
            $profit = round($sellingPrice - $totalCost, 2);
            // Profit margin is profit as a percentage of selling price.
            // (profit / cost) would be markup, which is a different metric.
            $profitMargin = $sellingPrice > 0 ? round(($profit / $sellingPrice) * 100, 2) : 0;

            ProductCostSummary::updateOrCreate(
                ['product_id' => $productId],
                [
                    'materials_cost' => $materialsCost,
                    'extra_cost' => $extraCost,
                    'total_cost' => $totalCost,
                    'selling_price' => $sellingPrice,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                ]
            );

            Product::whereKey($productId)->update([
                'cost_price' => $totalCost,
            ]);
        });

        return redirect()
            ->route('admin.cost-calculator.index', ['product_id' => $data['product_id']])
            ->with('success', __('Product cost saved successfully.'));
    }
}
