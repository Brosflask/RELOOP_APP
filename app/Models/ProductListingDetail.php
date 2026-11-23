<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Developer 3. */
class ProductListingDetail extends Model
{
    protected $fillable = [
        'listing_id', 'product_category', 'product_condition', 'production_quantity',
        'available_quantity', 'unit',
    ];

    protected function casts(): array
    {
        return [
            'production_quantity' => 'integer',
            'available_quantity' => 'decimal:3',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }
}
