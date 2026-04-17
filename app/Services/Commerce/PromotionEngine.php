<?php

namespace App\Services\Commerce;

use App\Models\PromotionRule;
use Illuminate\Support\Collection;

class PromotionEngine
{
    public function resolve(Collection $items, float $subtotal): array
    {
        $rules = PromotionRule::active()->orderByDesc('priority')->orderBy('id')->get();
        $best = ['discount' => 0.0, 'label' => null, 'rule' => null];

        foreach ($rules as $rule) {
            if ($rule->min_subtotal && $subtotal < (float) $rule->min_subtotal) {
                continue;
            }

            $discount = match ($rule->type) {
                PromotionRule::TYPE_ORDER_PERCENTAGE => round($subtotal * ((float) $rule->discount_value / 100), 2),
                PromotionRule::TYPE_ORDER_FIXED => min($subtotal, (float) $rule->discount_value),
                PromotionRule::TYPE_CATEGORY_PERCENTAGE => $this->categoryDiscount($items, $rule),
                PromotionRule::TYPE_BUY_X_GET_Y => $this->buyXGetYDiscount($items, $rule),
                default => 0,
            };

            if ($discount > $best['discount']) {
                $best = ['discount' => round($discount, 2), 'label' => $rule->name, 'rule' => $rule];
            }
        }

        return $best;
    }

    protected function categoryDiscount(Collection $items, PromotionRule $rule): float
    {
        $eligible = $items->filter(fn ($item) => (int) optional($item->product)->category_id === (int) $rule->category_id);
        $eligibleSubtotal = (float) $eligible->sum('line_total');
        return round($eligibleSubtotal * ((float) $rule->discount_value / 100), 2);
    }

    protected function buyXGetYDiscount(Collection $items, PromotionRule $rule): float
    {
        $eligible = $items
            ->filter(fn ($item) => ! $rule->category_id || (int) optional($item->product)->category_id === (int) $rule->category_id)
            ->sortBy('unit_price')
            ->values();

        $qty = (int) $eligible->sum('quantity');
        $bundleSize = max(1, (int) $rule->buy_quantity + (int) $rule->get_quantity);
        $freeUnits = intdiv($qty, $bundleSize) * (int) $rule->get_quantity;

        $discount = 0;
        foreach ($eligible as $item) {
            $lineFree = min($freeUnits, (int) $item->quantity);
            $discount += $lineFree * (float) $item->unit_price;
            $freeUnits -= $lineFree;
            if ($freeUnits <= 0) { break; }
        }
        return round($discount, 2);
    }
}
