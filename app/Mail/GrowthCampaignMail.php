<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GrowthCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function build(): static
    {
        return $this->subject((string) ($this->payload['subject'] ?? __('Growth campaign')))
            ->view('emails.growth.campaign')
            ->with($this->payload);
    }
}
