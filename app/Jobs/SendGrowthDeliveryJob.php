<?php

namespace App\Jobs;

use App\Services\Growth\GrowthDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendGrowthDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $deliveryId)
    {
    }

    public function handle(GrowthDeliveryService $deliveryService): void
    {
        $deliveryService->send($this->deliveryId);
    }

    public function failed(\Throwable $exception): void
    {
        app(GrowthDeliveryService::class)->markFailed($this->deliveryId, $exception);
    }
}
