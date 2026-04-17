<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPaymentStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Order $order, protected Payment $payment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_status_updated',
            'icon' => 'mdi-credit-card-check-outline',
            'title' => __('Payment update'),
            'body' => __('Payment for order :order is now :status.', ['order' => $this->order->order_number, 'status' => $this->payment->status_label]),
            'order_id' => $this->order->id,
            'payment_id' => $this->payment->id,
            'payment_status' => $this->payment->status,
            'action_url' => route('orders.show', $this->order),
        ];
    }
}
