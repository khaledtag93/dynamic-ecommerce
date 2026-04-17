<?php

namespace App\Services\Growth;

use App\Models\GrowthCampaign;
use App\Models\User;

class GrowthTimingOptimizer
{
    public function recommend(GrowthCampaign $campaign, array $snapshot = [], ?User $user = null): array
    {
        $timezone = config('app.timezone', 'UTC');
        $now = now($timezone);

        $baseMinutes = match ($campaign->campaign_key) {
            'abandoned_checkout' => 20,
            'cart_recovery' => 35,
            'high_intent_users' => 15,
            'repeat_buyer' => 180,
            default => 30,
        };

        $candidate = $now->copy()->addMinutes($baseMinutes);
        $reason = 'base_delay';

        if ($campaign->channel === 'email') {
            if ((int) $candidate->format('H') < 10) {
                $candidate->setTime(10, 0);
                $reason = 'email_business_hours_morning';
            } elseif ((int) $candidate->format('H') >= 22) {
                $candidate = $candidate->addDay()->setTime(10, 0);
                $reason = 'email_business_hours_next_day';
            }
        } else {
            if ((int) $candidate->format('H') < 9) {
                $candidate->setTime(9, 0);
                $reason = 'in_app_morning_window';
            } elseif ((int) $candidate->format('H') >= 23) {
                $candidate = $candidate->addDay()->setTime(10, 0);
                $reason = 'in_app_next_day_window';
            }
        }

        if (($snapshot['reason'] ?? null) === 'high_intent_users' && (int) ($snapshot['view_count'] ?? 0) >= 6) {
            $fast = $now->copy()->addMinutes(10);
            if ($fast->lt($candidate)) {
                $candidate = $fast;
            }
            $reason = 'high_intent_fast_followup';
        }

        return [
            'scheduled_for' => $candidate,
            'reason' => $reason,
            'timing_score' => $this->score($candidate->format('H'), $campaign->channel),
        ];
    }

    protected function score(string $hour, string $channel): int
    {
        $h = (int) $hour;
        if ($channel === 'email') {
            return ($h >= 10 && $h <= 20) ? 92 : 65;
        }
        return ($h >= 9 && $h <= 22) ? 90 : 60;
    }
}
