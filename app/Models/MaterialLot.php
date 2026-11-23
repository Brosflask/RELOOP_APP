<?php

namespace App\Models;

use App\Enums\MaterialLotStatus;
use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Developer 3. A tracked quantity of processed material. Stock levels are not
 * stored here: InventoryService works them out from inventory_movements.
 */
class MaterialLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'owner_id', 'processing_record_id', 'parent_lot_id', 'source_order_item_id',
        'material_type', 'form', 'grade', 'initial_quantity', 'unit', 'status',
    ];

    protected function casts(): array
    {
        return [
            'material_type' => MaterialType::class,
            'status' => MaterialLotStatus::class,
            'initial_quantity' => 'decimal:3',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function processingRecord(): BelongsTo
    {
        return $this->belongsTo(ProcessingRecord::class);
    }

    public function parentLot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lot_id');
    }

    public function childLots(): HasMany
    {
        return $this->hasMany(self::class, 'parent_lot_id');
    }

    public function sourceOrderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'source_order_item_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** The raw-material listing currently selling this lot, if any. */
    public function rawMaterialListingDetail(): HasOne
    {
        return $this->hasOne(RawMaterialListingDetail::class)->latestOfMany();
    }

    /** Finished products made from this lot. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'product_materials')
            ->withPivot(['quantity_used', 'unit'])
            ->withTimestamps();
    }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        return $query->where('owner_id', $user instanceof User ? $user->id : $user);
    }
}
