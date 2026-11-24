<?php

namespace App\Services\Recycling;

use App\Models\MaterialLot;
use App\Support\Money;

/** Developer 3. The props React gets for a material lot. */
class LotPresenter
{
    /** @param array<int, string> $available lot id => available quantity */
    public static function row(MaterialLot $lot, array $available): array
    {
        return [
            'id' => $lot->id,
            'code' => $lot->code,
            'material_type' => $lot->material_type->value,
            'material_type_label' => $lot->material_type->label(),
            'form' => $lot->form,
            'grade' => $lot->grade,
            'initial_quantity' => Money::quantity((string) $lot->initial_quantity),
            'available_quantity' => $available[$lot->id] ?? Money::quantity(0),
            'unit' => $lot->unit,
            'status' => $lot->status->value,
            'status_label' => $lot->status->label(),
            'is_bought' => $lot->parent_lot_id !== null,
            'created_at' => $lot->created_at->toIso8601String(),
        ];
    }
}
