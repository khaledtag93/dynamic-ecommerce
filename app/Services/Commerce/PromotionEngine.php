<?php

namespace App\Services\Commerce;

use App\Models\PromotionRule;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

class PromotionEngine
{
    public function resolve(Collection $items, float $subtotal): array
    {
        $rules = PromotionRule::active()->orderByDesc('priority')->orderBy('id')->get();
        $subtotalAmount = $this->moneyAmount($subtotal);
        $bestDiscount = BigDecimal::of('0.00');
        $bestLabel = null;
        $bestRule = null;

        foreach ($rules as $rule) {
            if ($rule->min_subtotal !== null) {
                $minimumSubtotal = $this->storedMoney($rule->min_subtotal);

                if ($subtotalAmount->compareTo($minimumSubtotal) < 0) {
                    continue;
                }
            }

            $discount = match ($rule->type) {
                PromotionRule::TYPE_ORDER_PERCENTAGE => $this->percentageDiscount($subtotalAmount, $rule),
                PromotionRule::TYPE_ORDER_FIXED => $this->storedMoney($rule->discount_value),
                PromotionRule::TYPE_CATEGORY_PERCENTAGE => $this->categoryDiscount($items, $rule),
                PromotionRule::TYPE_BUY_X_GET_Y => $this->buyXGetYDiscount($items, $rule),
                default => BigDecimal::of('0.00'),
            };

            $discount = $this->clampDiscount($discount, $subtotalAmount);

            if ($discount->compareTo($bestDiscount) > 0) {
                $bestDiscount = $discount;
                $bestLabel = $rule->name;
                $bestRule = $rule;
            }
        }

        return [
            'discount' => (float) (string) $bestDiscount->toScale(2, RoundingMode::Unnecessary),
            'label' => $bestLabel,
            'rule' => $bestRule,
        ];
    }

    protected function categoryDiscount(Collection $items, PromotionRule $rule): BigDecimal
    {
        $eligible = $items->filter(
            fn ($item) => (int) optional($item->product)->category_id === (int) $rule->category_id
        );

        $eligibleSubtotal = BigDecimal::of('0.00');

        foreach ($eligible as $item) {
            $eligibleSubtotal = $eligibleSubtotal->plus(
                $this->moneyAmount($item->line_total)
            );
        }

        return $this->percentageDiscount($eligibleSubtotal, $rule);
    }

    protected function buyXGetYDiscount(Collection $items, PromotionRule $rule): BigDecimal
    {
        $eligible = $items
            ->filter(fn ($item) => ! $rule->category_id || (int) optional($item->product)->category_id === (int) $rule->category_id)
            ->sortBy('unit_price')
            ->values();

        $qty = (int) $eligible->sum('quantity');
        $bundleSize = max(1, (int) $rule->buy_quantity + (int) $rule->get_quantity);
        $freeUnits = intdiv($qty, $bundleSize) * (int) $rule->get_quantity;

        $discount = BigDecimal::of('0.00');

        foreach ($eligible as $item) {
            $lineFree = min($freeUnits, (int) $item->quantity);

            if ($lineFree > 0) {
                $lineDiscount = $this->moneyAmount($item->unit_price)
                    ->multipliedBy($lineFree)
                    ->toScale(2, RoundingMode::Unnecessary);

                $discount = $discount->plus($lineDiscount);
            }

            $freeUnits -= $lineFree;

            if ($freeUnits <= 0) {
                break;
            }
        }

        return $discount->toScale(2, RoundingMode::Unnecessary);
    }

    private function percentageDiscount(BigDecimal $baseAmount, PromotionRule $rule): BigDecimal
    {
        $percentage = $this->storedMoney($rule->discount_value);

        return $baseAmount
            ->multipliedBy($percentage)
            ->dividedBy('100', 2, RoundingMode::HalfUp);
    }

    private function clampDiscount(BigDecimal $discount, BigDecimal $subtotal): BigDecimal
    {
        if ($discount->compareTo('0.00') < 0) {
            return BigDecimal::of('0.00');
        }

        if ($discount->compareTo($subtotal) > 0) {
            return $subtotal;
        }

        return $discount->toScale(2, RoundingMode::Unnecessary);
    }

    private function storedMoney(mixed $amount): BigDecimal
    {
        if ($amount === null || $amount === '') {
            return BigDecimal::of('0.00');
        }

        return BigDecimal::of((string) $amount)
            ->toScale(2, RoundingMode::Unnecessary);
    }

    private function moneyAmount(mixed $amount): BigDecimal
    {
        return BigDecimal::of((string) $amount)
            ->toScale(2, RoundingMode::HalfUp);
    }
}
