<?php

namespace App\Services\Marketplace;

use App\Http\Requests\Marketplace\StoreProductListingRequest;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Support\Money;

/** Developer 3. The props React gets for listings. */
class ListingPresenter
{
    public static function label(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(array $values): array
    {
        return array_map(fn ($v) => ['value' => $v, 'label' => self::label($v)], $values);
    }

    public static function categoryOptions(): array
    {
        return self::options(StoreProductListingRequest::CATEGORIES);
    }

    /** Card in the marketplace grid and seller lists. Uses the cached available quantity. */
    public static function card(Listing $listing): array
    {
        $raw = $listing->isRawMaterial();
        $detail = $raw ? $listing->rawMaterialDetail : $listing->productDetail;
        $image = $listing->images->first();

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'listing_type' => $listing->listing_type->value,
            'listing_type_label' => $listing->listing_type->label(),
            'price' => (string) $listing->price,
            'currency' => $listing->currency,
            'unit' => $detail->unit ?? $listing->listing_type->defaultUnit(),
            'available_quantity' => Money::quantity((string) ($detail->available_quantity ?? 0)),
            'subtitle' => $raw
                ? $listing->rawMaterialDetail?->material_type?->label()
                : self::label((string) $listing->productDetail?->product_category),
            'location' => $listing->location,
            'status' => $listing->status->value,
            'status_label' => $listing->status->label(),
            'image_url' => $image?->url,
            'seller' => $listing->relationLoaded('seller') ? ['id' => $listing->seller->id, 'name' => $listing->seller->name] : null,
            'published_at' => $listing->published_at?->toIso8601String(),
        ];
    }

    /** Origin of a lot, followed back through any purchases to the processing run. */
    public static function origin(MaterialLot $lot): array
    {
        $source = $lot;
        $hops = 0;
        while ($source->parent_lot_id && $hops++ < 10) {
            $source = $source->parentLot;
        }
        $record = $source->processingRecord;

        return [
            'lot_code' => $lot->code,
            'material_type_label' => $lot->material_type->label(),
            'form' => $lot->form,
            'grade' => $lot->grade,
            'original_lot_code' => $source->id !== $lot->id ? $source->code : null,
            'processing' => $record ? [
                'reference' => $record->reference,
                'process_type' => $record->process_type,
                'processed_at' => $record->processed_at->toIso8601String(),
                'material_batch_id' => $record->material_batch_id,
                'input_weight_kg' => (string) $record->input_weight_kg,
                'output_weight_kg' => (string) $record->output_weight_kg,
            ] : null,
        ];
    }
}
