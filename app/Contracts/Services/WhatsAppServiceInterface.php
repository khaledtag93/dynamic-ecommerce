<?php

namespace App\Contracts\Services;

use App\Models\Order;
use App\Models\WhatsAppLog;

interface WhatsAppServiceInterface
{
    public function queueOrderConfirmation(Order $order): void;

    public function queueOrderStatusUpdate(Order $order): void;

    public function queueDeliveryUpdate(Order $order): void;

    public function sendOrderConfirmation(Order|int $order): ?WhatsAppLog;

    public function sendOrderStatusUpdate(Order|int $order): ?WhatsAppLog;

    public function sendDeliveryUpdate(Order|int $order): ?WhatsAppLog;

    public function retry(WhatsAppLog|int $log): ?WhatsAppLog;
}
