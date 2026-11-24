<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Developer 3. One row of the append-only stock ledger. Only InventoryService
 * writes these; rows are never changed or deleted after they are written.
 */
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'material_lot_id', 'listing_id', 'order_id', 'order_item_id', 'movement_type',
        'quantity_change', 'reserved_change', 'unit', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity_change' => 'decimal:3',
            'reserved_change' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory movements cannot be changed. Write an adjustment instead.'));
        static::deleting(fn () => throw new LogicException('Inventory movements cannot be deleted. Write an adjustment instead.'));
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
