<?php

namespace App\Services\Growth\Channels;

use App\Models\GrowthCampaign;
use App\Models\GrowthDelivery;
use App\Models\User;
use App\Notifications\GrowthCampaignNotification;
use App\Services\Growth\Contracts\GrowthChannelDriver;

class InAppGrowthChannel implements GrowthChannelDriver
{
    public function send(GrowthDelivery $delivery, ?User $user, array $payload): array
    {
        if (! $user) {
            return [
                'status' => 'skipped',
                'recipient' => null,
                'meta' => ['reason' => 'missing_user_for_in_app'],
            ];
        }

        $campaign = $delivery->campaign ?: new GrowthCampaign();
        $user->notify(new GrowthCampaignNotification($campaign, (string) ($payload['message'] ?? ''), (string) ($payload['subject'] ?? '')));

        return [
            'status' => 'sent',
            'recipient' => 'user:'.$user->id,
        ];
    }
}
