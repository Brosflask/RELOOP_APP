<?php

namespace App\Models;

use App\Enums\CollectionStatus;
use App\Models\Concerns\HasReferenceCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Developer 2. A household's request for a pickup. */
class CollectionRequest extends Model
{
    use HasFactory, HasReferenceCode;

    public const REFERENCE_COLUMN = 'reference';

    public const REFERENCE_PREFIX = 'CR';

    protected $fillable = [
        'reference', 'household_id', 'collector_id', 'assigned_by', 'status',
        'pickup_address', 'area', 'gps_address', 'latitude', 'longitude', 'contact_phone',
        'preferred_date', 'preferred_time_slot', 'estimated_weight_kg', 'notes',
        'assigned_at', 'started_at', 'completed_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectionStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'preferred_date' => 'date',
            'estimated_weight_kg' => 'decimal:3',
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(User::class, 'household_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CollectionRequestItem::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(CollectionRecord::class);
    }

    /** Pending, assigned or in progress. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CollectionStatus::Pending->value, CollectionStatus::Assigned->value, CollectionStatus::InProgress->value,
        ]);
    }

    /** Waiting for a collector to accept it. */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where('status', CollectionStatus::Pending->value)->whereNull('collector_id');
    }
}
