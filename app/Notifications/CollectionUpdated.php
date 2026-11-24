<?php

namespace App\Notifications;

use App\Models\CollectionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Developer 2. In-app notice about a pickup (stored in Developer 4's notifications table). */
class CollectionUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?CollectionRequest $request, public string $title, public string $body) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'collection_update',
            'title' => $this->title,
            'body' => $this->body,
            'collection_request_id' => $this->request?->id,
            'collection_request_reference' => $this->request?->reference,
        ];
    }
}
