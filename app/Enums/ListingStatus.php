<?php

namespace App\Enums;

/**
 * Listing lifecycle. Only Active listings can be ordered
 * (Developer 4's ListingTerms::ORDERABLE_STATUS = 'active').
 */
enum ListingStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case SoldOut = 'sold_out';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::SoldOut => 'Sold out',
            self::Closed => 'Closed',
        };
    }

    public function isOrderable(): bool
    {
        return $this === self::Active;
    }

    /** Statuses that still hold a material lot, so the lot cannot be listed again. */
    public static function holdingStock(): array
    {
        return [self::Draft, self::Active, self::Paused, self::SoldOut];
    }
}
