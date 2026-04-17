<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DeliveryStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'delivery_status_updated',
            'icon' => 'mdi-truck-fast-outline',
            'title' => __('Delivery update'),
            'body' => __('Delivery for order :order is now :status.', ['order' => $this->order->order_number, 'status' => $this->order->delivery_status_label]),
            'order_id' => $this->order->id,
            'action_url' => route('orders.show', $this->order),
        ];
    }
}
