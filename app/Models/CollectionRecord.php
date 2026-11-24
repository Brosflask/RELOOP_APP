<?php

namespace App\Models;

use App\Enums\CollectionRecordStatus;
use App\Enums\MaterialType;
use App\Models\Concerns\HasReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/** Developer 2. What a collector actually collected (one material type, weighed). */
class CollectionRecord extends Model
{
    use HasFactory, HasReferenceCode;

    public const REFERENCE_COLUMN = 'reference';

    public const REFERENCE_PREFIX = 'COL';

    protected $fillable = [
        'reference', 'collection_request_id', 'collector_id', 'household_id', 'material_type',
        'actual_weight_kg', 'collected_at', 'status', 'confirmed_at', 'disputed_at',
        'dispute_reason', 'photo_path', 'notes',
    ];

    protected $attributes = [
        'status' => 'pending_confirmation',
    ];

    protected function casts(): array
    {
        return [
            'material_type' => MaterialType::class,
            'status' => CollectionRecordStatus::class,
            'actual_weight_kg' => 'decimal:3',
            'collected_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    public function collectionRequest(): BelongsTo
    {
        return $this->belongsTo(CollectionRequest::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(User::class, 'household_id');
    }

    public function batchCollection(): HasOne
    {
        return $this->hasOne(BatchCollection::class);
    }

    /** The batch this record was put in, if any. */
    public function batch(): HasOneThrough
    {
        return $this->hasOneThrough(
            MaterialBatch::class, BatchCollection::class,
            'collection_record_id', 'id', 'id', 'material_batch_id'
        );
    }

    /** Not yet in any batch. */
    public function scopeUnbatched(Builder $query): Builder
    {
        return $query->whereDoesntHave('batchCollection');
    }
}
