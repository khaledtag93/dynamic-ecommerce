<?php

namespace App\Jobs;

use App\Contracts\Services\WhatsAppServiceInterface;
use App\Enums\WhatsAppMessageType;
use App\Services\Channels\WhatsApp\Support\WhatsAppConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;
    public int $timeout;
    public array|int $backoff;

    public function __construct(
        public int $orderId,
        public string $messageType,
    ) {
        $config = app(WhatsAppConfig::class);
        $this->tries = $config->queueTries();
        $this->timeout = $config->queueTimeout();
        $this->backoff = $config->queueBackoffSchedule();
    }

    public function handle(WhatsAppServiceInterface $service): void
    {
        match ($this->messageType) {
            WhatsAppMessageType::ORDER_CONFIRMATION->value => $service->sendOrderConfirmation($this->orderId),
            WhatsAppMessageType::ORDER_STATUS_UPDATE->value => $service->sendOrderStatusUpdate($this->orderId),
            WhatsAppMessageType::DELIVERY_UPDATE->value => $service->sendDeliveryUpdate($this->orderId),
            default => null,
        };
    }
}
