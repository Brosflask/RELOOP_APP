<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Offer $offer)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New offer from '.$this->offer->maker->name)
            ->line(sprintf('%s offered GHS %s per unit for %s.', $this->offer->maker->name, $this->offer->unit_price, $this->offer->quantity))
            ->action('Reply to the offer', route('messages.show', $this->offer->conversation_id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'offer_update',
            'title' => 'New offer from '.$this->offer->maker->name,
            'body' => sprintf('GHS %s per unit for %s', $this->offer->unit_price, $this->offer->quantity),
            'conversation_id' => $this->offer->conversation_id,
            'offer_id' => $this->offer->id,
        ];
    }
}
