<?php

namespace App\Models;

use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Developer 3. */
class RawMaterialListingDetail extends Model
{
    protected $fillable = [
        'listing_id', 'material_lot_id', 'material_type', 'available_quantity',
        'minimum_order_quantity', 'unit',
    ];

    protected function casts(): array
    {
        return [
            'material_type' => MaterialType::class,
            'available_quantity' => 'decimal:3',
            'minimum_order_quantity' => 'decimal:3',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function materialLot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class);
    }
}
