<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Developer 2. One link: this material lot was made from this batch. */
class MaterialLineage extends Model
{
    protected $table = 'material_lineage';

    protected $fillable = ['material_lot_id', 'material_batch_id', 'processing_record_id', 'contributed_weight_kg'];

    protected function casts(): array
    {
        return ['contributed_weight_kg' => 'decimal:3'];
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }

    public function materialBatch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class);
    }

    public function processingRecord(): BelongsTo
    {
        return $this->belongsTo(ProcessingRecord::class);
    }
}
