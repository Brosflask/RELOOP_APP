<?php

namespace App\Services\Collection;

use App\Models\CollectionRecord;
use App\Models\CollectionRequest;

/** Developer 2. Turns models into the plain arrays the React pages receive (weights as strings). */
class CollectionPresenter
{
    public static function request(CollectionRequest $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'pickup_address' => $r->pickup_address,
            'area' => $r->area,
            'gps_address' => $r->gps_address,
            'contact_phone' => $r->contact_phone,
            'preferred_date' => $r->preferred_date?->toDateString(),
            'preferred_time_slot' => $r->preferred_time_slot,
            'estimated_weight_kg' => $r->estimated_weight_kg !== null ? (string) $r->estimated_weight_kg : null,
            'notes' => $r->notes,
            'household' => $r->relationLoaded('household') && $r->household ? ['id' => $r->household->id, 'name' => $r->household->name] : null,
            'collector' => $r->relationLoaded('collector') && $r->collector ? ['id' => $r->collector->id, 'name' => $r->collector->name] : null,
            'items' => $r->relationLoaded('items') ? $r->items->map(fn ($i) => [
                'material_type' => $i->material_type->value,
                'material_label' => $i->material_type->label(),
                'estimated_weight_kg' => $i->estimated_weight_kg !== null ? (string) $i->estimated_weight_kg : null,
            ])->values() : [],
            'assigned_at' => $r->assigned_at?->toIso8601String(),
            'started_at' => $r->started_at?->toIso8601String(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'cancelled_at' => $r->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $r->cancellation_reason,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    public static function record(CollectionRecord $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'collection_request_id' => $r->collection_request_id,
            'material_type' => $r->material_type->value,
            'material_label' => $r->material_type->label(),
            'actual_weight_kg' => (string) $r->actual_weight_kg,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'collected_at' => $r->collected_at->toIso8601String(),
            'dispute_reason' => $r->dispute_reason,
            'notes' => $r->notes,
            'in_batch' => $r->relationLoaded('batchCollection') ? $r->batchCollection !== null : null,
        ];
    }
}
