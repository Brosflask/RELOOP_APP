<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** In-app (database) and email notice about an order or its payment. */
class OrderUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public string $title, public string $body)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' · '.$this->order->code)
            ->line($this->body)
            ->action('View order', route('orders.show', $this->order));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_update',
            'title' => $this->title,
            'body' => $this->body,
            'order_id' => $this->order->id,
            'order_code' => $this->order->code,
        ];
    }
}
