<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Developer 3. Links a finished product to a lot it was made from. */
class ProductMaterial extends Model
{
    protected $fillable = ['listing_id', 'material_lot_id', 'quantity_used', 'unit'];

    protected function casts(): array
    {
        return ['quantity_used' => 'decimal:3'];
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
