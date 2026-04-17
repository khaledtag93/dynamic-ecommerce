<?php

namespace App\Notifications;

use App\Models\GrowthCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GrowthCampaignNotification extends Notification
{
    use Queueable;

    public function __construct(protected GrowthCampaign $campaign, protected string $message, protected ?string $title = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'growth_campaign',
            'icon' => 'mdi-rocket-launch-outline',
            'title' => $this->title ?: ($this->campaign->subject ?: __('Growth campaign')),
            'body' => $this->message,
            'campaign_id' => $this->campaign->id,
            'action_url' => route('frontend.home'),
        ];
    }
}
