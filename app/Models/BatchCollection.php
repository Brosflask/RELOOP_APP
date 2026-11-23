<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Developer 2. A collection record placed in a batch (a record is in one batch only). */
class BatchCollection extends Pivot
{
    use HasFactory;

    protected $table = 'batch_collections';

    public $incrementing = true;

    protected $fillable = ['material_batch_id', 'collection_record_id', 'weight_kg'];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:3'];
    }

    public function materialBatch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class);
    }

    public function collectionRecord(): BelongsTo
    {
        return $this->belongsTo(CollectionRecord::class);
    }
}
