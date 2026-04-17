<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $event,
        public string $title,
        public string $message,
    ) {
    }

    public function build(): static
    {
        return $this->subject($this->title.' - '.$this->order->order_number)
            ->view('emails.orders.update')
            ->with([
                'order' => $this->order,
                'event' => $this->event,
                'title' => $this->title,
                'message' => $this->message,
            ]);
    }
}
