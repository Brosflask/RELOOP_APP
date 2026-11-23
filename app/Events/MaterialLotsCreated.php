<?php

namespace App\Events;

use App\Models\ProcessingRecord;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Developer 3. Fired after a processing record and its lots are saved.
 * Developer 2 can listen to write material_lineage rows.
 */
class MaterialLotsCreated
{
    use Dispatchable;

    public function __construct(public readonly ProcessingRecord $record)
    {
    }
}
