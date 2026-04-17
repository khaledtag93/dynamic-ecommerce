<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Order $order, protected ?Payment $payment = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_placed',
            'icon' => 'mdi-receipt-text-outline',
            'title' => __('Order placed successfully'),
            'body' => __('Your order :order is now recorded and waiting for the next step.', ['order' => $this->order->order_number]),
            'order_id' => $this->order->id,
            'payment_id' => $this->payment?->id,
            'action_url' => route('orders.show', $this->order),
        ];
    }
}
