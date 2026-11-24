<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Developer 2. A recycler's confirmation that it received a batch, with the weight it measured. */
class BatchReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_batch_id', 'recycler_id', 'expected_weight_kg', 'received_weight_kg',
        'weight_difference_kg', 'received_at', 'condition_notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_weight_kg' => 'decimal:3',
            'received_weight_kg' => 'decimal:3',
            'weight_difference_kg' => 'decimal:3',
            'received_at' => 'datetime',
        ];
    }

    public function materialBatch(): BelongsTo
    {
        return $this->belongsTo(MaterialBatch::class);
    }

    public function recycler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recycler_id');
    }
}
