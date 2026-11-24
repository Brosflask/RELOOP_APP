<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Developer 3. Anything for sale on the marketplace. */
class Listing extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'seller_id', 'listing_type', 'title', 'description', 'price', 'currency',
        'status', 'location', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'listing_type' => ListingType::class,
            'status' => ListingStatus::class,
            'price' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function rawMaterialDetail(): HasOne
    {
        return $this->hasOne(RawMaterialListingDetail::class);
    }

    public function productDetail(): HasOne
    {
        return $this->hasOne(ProductListingDetail::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function productMaterials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class);
    }

    /** Lots a finished product was made from. */
    public function sourceLots(): BelongsToMany
    {
        return $this->belongsToMany(MaterialLot::class, 'product_materials')
            ->withPivot(['quantity_used', 'unit'])
            ->withTimestamps();
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** Developer 4's order lines for this listing. */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isRawMaterial(): bool
    {
        return $this->listing_type === ListingType::RawMaterial;
    }

    public function unit(): string
    {
        return $this->isRawMaterial()
            ? ($this->rawMaterialDetail?->unit ?? 'kg')
            : ($this->productDetail?->unit ?? 'piece');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Active->value);
    }

    public function scopeOfType(Builder $query, ListingType|string $type): Builder
    {
        return $query->where('listing_type', $type instanceof ListingType ? $type->value : $type);
    }
}
