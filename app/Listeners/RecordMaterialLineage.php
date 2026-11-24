<?php

namespace App\Listeners;

use App\Events\MaterialLotsCreated;
use App\Models\MaterialLineage;

/**
 * Developer 2. When Developer 3 saves a processing record and its lots, link every
 * new lot to the batch it was made from. Runs inside Developer 3's transaction
 * (not queued), so the lots and their lineage are saved together or not at all.
 * Laravel finds this listener automatically from the handle() type-hint.
 */
class RecordMaterialLineage
{
    public function handle(MaterialLotsCreated $event): void
    {
        $record = $event->record;
        foreach ($record->materialLots()->get() as $lot) {
            MaterialLineage::firstOrCreate(
                ['material_lot_id' => $lot->id, 'material_batch_id' => $record->material_batch_id],
                ['processing_record_id' => $record->id, 'contributed_weight_kg' => $lot->initial_quantity],
            );
        }
    }
}
