<?php

namespace App\Services\Orders;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;

/**
 * Reads the sale terms of a listing from Developer 3's agreed tables
 * (listings, raw_material_listing_details, product_listing_details) without
 * depending on their model relationship names. Read-only.
 */
final readonly class ListingTerms
{
    /** Listing status that can be ordered. [GUESS] until Developer 3's ListingStatus enum lands. */
    public const ORDERABLE_STATUS = 'active';

    public function __construct(
        public int $listingId,
        public int $sellerId,
        public string $listingType,
        public string $title,
        public string $unitPrice,
        public string $unit,
        public ?int $materialLotId,
        public bool $orderable,
    ) {
    }

    private static function scalar(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }

    public static function for(Listing $listing): self
    {
        $type = self::scalar($listing->listing_type);
        $unit = 'piece';
        $lotId = null;

        if ($type === 'raw_material') {
            $detail = DB::table('raw_material_listing_details')->where('listing_id', $listing->id)->first();
            $unit = $detail->unit ?? 'kg';
            $lotId = $detail->material_lot_id ?? null;
        }

        $status = self::scalar($listing->status);

        return new self(
            listingId: $listing->id,
            sellerId: (int) $listing->seller_id,
            listingType: $type,
            title: (string) $listing->title,
            unitPrice: number_format((float) $listing->price, 2, '.', ''),
            unit: $unit,
            materialLotId: $lotId ? (int) $lotId : null,
            orderable: $status === self::ORDERABLE_STATUS,
        );
    }
}
