<?php

namespace App\Enums;

/** What a listing sells. Stored in listings.listing_type. Developer 4 reads these values. */
enum ListingType: string
{
    case RawMaterial = 'raw_material';
    case FinishedProduct = 'finished_product';

    public function label(): string
    {
        return match ($this) {
            self::RawMaterial => 'Raw material',
            self::FinishedProduct => 'Finished product',
        };
    }

    /** Unit that quantities of this kind of listing are sold in by default. */
    public function defaultUnit(): string
    {
        return match ($this) {
            self::RawMaterial => 'kg',
            self::FinishedProduct => 'piece',
        };
    }
}
