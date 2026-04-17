<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Order $order, protected string $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'icon' => 'mdi-truck-check-outline',
            'title' => __('Order update'),
            'body' => $this->message,
            'order_id' => $this->order->id,
            'action_url' => route('orders.show', $this->order),
        ];
    }
}
