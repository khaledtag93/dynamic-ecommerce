<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\GrowthExperiment;
use App\Models\User;
use Illuminate\Support\Arr;

class GrowthOfferDecisionEngine
{
    public function decide(GrowthCampaign $campaign, array $snapshot = [], ?User $user = null, ?GrowthExperiment $experiment = null, ?array $variant = null): array
    {
        $ordersCount = (int) ($snapshot['orders_count'] ?? 0);
        $viewCount = (int) ($snapshot['view_count'] ?? 0);
        $cartCount = (int) ($snapshot['cart_count'] ?? 0);

        $decision = [
            'offer_key' => 'soft_nudge',
            'offer_label' => __('Helpful reminder'),
            'discount_type' => null,
            'discount_value' => null,
            'coupon_days_valid' => 3,
            'reason' => 'default_soft_nudge',
        ];

        if ($campaign->campaign_key === 'repeat_buyer') {
            $decision = [
                'offer_key' => 'vip_repeat_10',
                'offer_label' => __('Repeat buyer reward'),
                'discount_type' => 'percent',
                'discount_value' => $ordersCount >= 4 ? 15 : 10,
                'coupon_days_valid' => 7,
                'reason' => 'repeat_buyer_score',
            ];
        } elseif ($campaign->campaign_key === 'abandoned_checkout') {
            $decision = [
                'offer_key' => 'checkout_rescue',
                'offer_label' => __('Checkout rescue'),
                'discount_type' => null,
                'discount_value' => null,
                'coupon_days_valid' => 2,
                'reason' => 'friction_reduction_first',
            ];
            if ($cartCount >= 2 || $viewCount >= 5) {
                $decision['discount_type'] = 'percent';
                $decision['discount_value'] = 5;
                $decision['offer_key'] = 'checkout_rescue_5';
                $decision['offer_label'] = __('Checkout comeback offer');
                $decision['reason'] = 'checkout_high_friction_detected';
            }
        } elseif ($campaign->campaign_key === 'cart_recovery') {
            $decision = [
                'offer_key' => 'cart_recovery_5',
                'offer_label' => __('Cart recovery offer'),
                'discount_type' => 'percent',
                'discount_value' => $cartCount >= 2 || $viewCount >= 6 ? 10 : 5,
                'coupon_days_valid' => 3,
                'reason' => 'cart_recovery_intent_score',
            ];
        } elseif ($campaign->campaign_key === 'high_intent_users') {
            $decision = [
                'offer_key' => 'high_intent_nudge',
                'offer_label' => __('High-intent nudge'),
                'discount_type' => null,
                'discount_value' => null,
                'coupon_days_valid' => 2,
                'reason' => 'high_intent_without_discount',
            ];
            if ($viewCount >= 6 || $cartCount >= 1) {
                $decision['discount_type'] = 'percent';
                $decision['discount_value'] = 5;
                $decision['offer_key'] = 'high_intent_5';
                $decision['offer_label'] = __('Decision helper offer');
                $decision['reason'] = 'high_intent_discount_unlock';
            }
        }

        if (is_array($variant)) {
            $decision['offer_key'] = Arr::get($variant, 'offer_key', $decision['offer_key']);
            $decision['offer_label'] = Arr::get($variant, 'offer_label', $decision['offer_label']);
            $decision['discount_type'] = Arr::get($variant, 'discount_type', $decision['discount_type']);
            $decision['discount_value'] = Arr::get($variant, 'discount_value', $decision['discount_value']);
            $decision['coupon_days_valid'] = (int) Arr::get($variant, 'coupon_days_valid', $decision['coupon_days_valid']);
            $decision['reason'] = Arr::get($variant, 'decision_reason', $decision['reason']);
        }

        if ($user && method_exists($user, 'getAttribute') && (bool) $user->getAttribute('is_vip') && ($decision['discount_type'] === 'percent')) {
            $decision['discount_value'] = min(20, max((float) $decision['discount_value'], 10));
            $decision['reason'] = 'vip_adjustment';
        }

        return $decision;
    }
}
