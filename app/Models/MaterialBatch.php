<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Enums\MaterialType;
use App\Models\Concerns\HasReferenceCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Developer 2. Collected waste grouped for a recycler. One batch can become
 * many material lots (Developer 3) through processing records.
 */
class MaterialBatch extends Model
{
    use HasFactory, HasReferenceCode;

    public const REFERENCE_COLUMN = 'code';

    public const REFERENCE_PREFIX = 'BATCH';

    protected $fillable = [
        'code', 'collector_id', 'material_type', 'total_weight_kg', 'status', 'recycler_id',
        'consolidated_into_id', 'dispatched_at', 'received_at', 'rejected_at', 'rejection_reason', 'notes',
    ];

    protected $attributes = [
        'status' => 'open',
        'total_weight_kg' => '0.000',
    ];

    protected function casts(): array
    {
        return [
            'material_type' => MaterialType::class,
            'status' => BatchStatus::class,
            'total_weight_kg' => 'decimal:3',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /** The recycler it was sent to. */
    public function recycler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recycler_id');
    }

    public function batchCollections(): HasMany
    {
        return $this->hasMany(BatchCollection::class);
    }

    public function collectionRecords(): BelongsToMany
    {
        return $this->belongsToMany(CollectionRecord::class, 'batch_collections')
            ->using(BatchCollection::class)
            ->withPivot('weight_kg')
            ->withTimestamps();
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(BatchReceipt::class);
    }

    /** The bigger batch this one was merged into. */
    public function consolidatedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'consolidated_into_id');
    }

    /** Smaller batches merged into this one. */
    public function consolidatedBatches(): HasMany
    {
        return $this->hasMany(self::class, 'consolidated_into_id');
    }

    /** Developer 3's processing runs that used this batch. */
    public function processingRecords(): HasMany
    {
        return $this->hasMany(ProcessingRecord::class);
    }
}
