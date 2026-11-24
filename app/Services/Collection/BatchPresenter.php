<?php

namespace App\Services\Collection;

use App\Models\MaterialBatch;

/** Developer 2. The data batch pages receive (weights as strings). */
class BatchPresenter
{
    public static function batch(MaterialBatch $b): array
    {
        return [
            'id' => $b->id,
            'code' => $b->code,
            'material_type' => $b->material_type->value,
            'material_label' => $b->material_type->label(),
            'total_weight_kg' => (string) $b->total_weight_kg,
            'status' => $b->status->value,
            'status_label' => $b->status->label(),
            'collector' => $b->relationLoaded('collector') && $b->collector ? ['id' => $b->collector->id, 'name' => $b->collector->name] : null,
            'recycler' => $b->relationLoaded('recycler') && $b->recycler ? ['id' => $b->recycler->id, 'name' => $b->recycler->name] : null,
            'consolidated_into' => $b->relationLoaded('consolidatedInto') && $b->consolidatedInto
                ? ['id' => $b->consolidatedInto->id, 'code' => $b->consolidatedInto->code] : null,
            'records_count' => $b->batch_collections_count ?? null,
            'notes' => $b->notes,
            'dispatched_at' => $b->dispatched_at?->toIso8601String(),
            'received_at' => $b->received_at?->toIso8601String(),
            'rejected_at' => $b->rejected_at?->toIso8601String(),
            'rejection_reason' => $b->rejection_reason,
            'created_at' => $b->created_at->toIso8601String(),
        ];
    }
}
