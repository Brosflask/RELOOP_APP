<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    /**
     * Status-related columns are deliberately not fillable: they change only
     * through PaymentService after a verified provider status check.
     */
    protected $fillable = [
        'order_id', 'payer_id', 'payee_id', 'provider', 'transaction_reference',
        'amount', 'currency', 'payer_msisdn', 'external_id',
    ];

    protected $attributes = [
        'status' => 'pending',
        'requires_attention' => false,
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'verified_at' => 'datetime',
            'requires_attention' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('created_at')->orderBy('id');
    }
}
