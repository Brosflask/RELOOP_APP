<?php

namespace App\Notifications;

use App\Models\MaterialBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Developer 2. In-app notice about a material batch (Developer 4's notifications table). */
class BatchUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MaterialBatch $batch, public string $title, public string $body) {}

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
            'material_batch_id' => $this->batch->id,
            'material_batch_code' => $this->batch->code,
        ];
    }
}
