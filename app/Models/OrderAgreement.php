<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'offer_id', 'unit_price', 'quantity', 'total_amount',
        'delivery_terms', 'payment_arrangement', 'buyer_accepted_at', 'seller_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:3',
            'total_amount' => 'decimal:2',
            'buyer_accepted_at' => 'datetime',
            'seller_accepted_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
