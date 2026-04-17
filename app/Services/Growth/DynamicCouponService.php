<?php

namespace App\Services\Growth;

use App\Models\Coupon;
use App\Models\GrowthCampaign;
use App\Models\GrowthCouponIssue;
use App\Models\GrowthTriggerLog;
use App\Models\User;
use Illuminate\Support\Str;

class DynamicCouponService
{
    public function issueForDecision(GrowthCampaign $campaign, GrowthTriggerLog $triggerLog, array $decision, ?User $user = null): ?array
    {
        $discountType = $decision['discount_type'] ?? null;
        $discountValue = $decision['discount_value'] ?? null;

        if (! $discountType || ! $discountValue) {
            return null;
        }

        if ($user && $existing = GrowthCouponIssue::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $user->id)
            ->where('offer_key', (string) ($decision['offer_key'] ?? ''))
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first()) {
            return [
                'coupon_code' => $existing->coupon_code,
                'coupon_id' => $existing->coupon_id,
                'offer_label' => $existing->offer_label,
                'discount_type' => $existing->discount_type,
                'discount_value' => (float) $existing->discount_value,
                'reason' => 'reused_active_coupon',
            ];
        }

        $code = $this->generateCode($campaign);
        $daysValid = max(1, (int) ($decision['coupon_days_valid'] ?? 3));
        $expiresAt = now()->addDays($daysValid)->endOfDay();

        $coupon = Coupon::query()->create([
            'name' => $campaign->name.' - '.($decision['offer_label'] ?? __('Growth offer')),
            'code' => $code,
            'type' => $discountType,
            'value' => $discountValue,
            'usage_limit' => 1,
            'used_count' => 0,
            'starts_at' => now(),
            'ends_at' => $expiresAt,
            'is_active' => true,
            'notes' => __('Auto-issued by growth engine.'),
        ]);

        GrowthCouponIssue::query()->create([
            'campaign_id' => $campaign->id,
            'trigger_log_id' => $triggerLog->id,
            'user_id' => $user?->id,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'offer_key' => (string) ($decision['offer_key'] ?? 'growth_offer'),
            'offer_label' => (string) ($decision['offer_label'] ?? __('Growth offer')),
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'expires_at' => $expiresAt,
            'meta' => ['decision' => $decision],
        ]);

        return [
            'coupon_code' => $coupon->code,
            'coupon_id' => $coupon->id,
            'offer_label' => (string) ($decision['offer_label'] ?? __('Growth offer')),
            'discount_type' => $discountType,
            'discount_value' => (float) $discountValue,
            'reason' => 'issued_dynamic_coupon',
        ];
    }

    protected function generateCode(GrowthCampaign $campaign): string
    {
        do {
            $prefix = Str::upper(Str::substr(Str::slug($campaign->campaign_key, ''), 0, 4));
            $code = $prefix.'-'.Str::upper(Str::random(8));
        } while (Coupon::query()->where('code', $code)->exists());

        return $code;
    }
}
