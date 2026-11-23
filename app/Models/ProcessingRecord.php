<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Developer 3. One processing run of a received batch: weights in, out and lost. */
class ProcessingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'recycler_id', 'material_batch_id', 'process_type',
        'input_weight_kg', 'output_weight_kg', 'loss_weight_kg', 'processed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'input_weight_kg' => 'decimal:3',
            'output_weight_kg' => 'decimal:3',
            'loss_weight_kg' => 'decimal:3',
            'processed_at' => 'datetime',
        ];
    }

    public function recycler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recycler_id');
    }

    /** Developer 2's model. */
    public function materialBatch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class);
    }

    public function materialLots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }
}
