<?php

namespace App\Services\Growth\Channels;

use App\Mail\GrowthCampaignMail;
use App\Models\GrowthDelivery;
use App\Models\User;
use App\Services\Growth\Contracts\GrowthChannelDriver;
use Illuminate\Support\Facades\Mail;

class SmtpEmailGrowthChannel implements GrowthChannelDriver
{
    public function send(GrowthDelivery $delivery, ?User $user, array $payload): array
    {
        $email = $user?->email ?: (string) ($delivery->recipient ?? '');

        if ($email === '') {
            return [
                'status' => 'skipped',
                'recipient' => null,
                'meta' => ['reason' => 'missing_email'],
            ];
        }

        Mail::to($email)->send(new GrowthCampaignMail($payload));

        return [
            'status' => 'sent',
            'recipient' => $email,
            'meta' => ['provider' => 'smtp'],
        ];
    }
}
