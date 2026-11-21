<?php

namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id', 'listing_id', 'made_by', 'parent_offer_id',
        'unit_price', 'quantity', 'message', 'status', 'expires_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'unit_price' => 'decimal:2',
            'quantity' => 'decimal:3',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'made_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_offer_id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(self::class, 'parent_offer_id');
    }

    public function isPending(): bool
    {
        return $this->status === OfferStatus::Pending
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
