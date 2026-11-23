<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Exceptions\BusinessRuleException;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\MaterialLot;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Developer 3. Creates and manages marketplace listings. Stock is never set
 * here directly: it comes from the lot (raw material) or from the opening
 * stock row InventoryService writes for a product.
 */
class ListingService
{
    public const MAX_IMAGES = 5;

    /** Which status changes a seller may make. Sold out is set by InventoryService only. */
    private const SELLER_TRANSITIONS = [
        'draft' => ['active', 'closed'],
        'active' => ['paused', 'closed'],
        'paused' => ['active', 'closed'],
        'sold_out' => ['closed'],
        'closed' => [],
    ];

    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /** @param array{material_lot_id: int, title: string, description?: ?string, price: string, location?: ?string, minimum_order_quantity?: ?string, publish?: bool} $data */
    public function createRawMaterialListing(User $seller, array $data): Listing
    {
        return DB::transaction(function () use ($seller, $data) {
            $lot = MaterialLot::query()->lockForUpdate()->findOrFail($data['material_lot_id']);

            if ($lot->owner_id !== $seller->id) {
                throw new BusinessRuleException('You can only list lots you own.', 'material_lot_id');
            }
            if ($this->lotIsListed($lot)) {
                throw new BusinessRuleException('This lot already has a listing. Edit that listing instead.', 'material_lot_id');
            }
            $available = $this->inventory->availableQuantityForLot($lot);
            if (Money::toMilli($available) <= 0) {
                throw new BusinessRuleException('This lot has no stock left to sell.', 'material_lot_id');
            }
            if (! empty($data['minimum_order_quantity']) && Money::compareQuantity($data['minimum_order_quantity'], $available) > 0) {
                throw new BusinessRuleException('The minimum order is more than the stock in this lot.', 'minimum_order_quantity');
            }

            $listing = Listing::create($this->baseAttributes($seller, ListingType::RawMaterial, $data));
            $listing->rawMaterialDetail()->create([
                'material_lot_id' => $lot->id,
                'material_type' => $lot->material_type,
                'available_quantity' => $available,
                'minimum_order_quantity' => $data['minimum_order_quantity'] ?? null,
                'unit' => $lot->unit,
            ]);

            return $listing->load('rawMaterialDetail');
        });
    }

    /**
     * @param array{title: string, description?: ?string, price: string, location?: ?string, product_category: string,
     *              product_condition: string, production_quantity: int, publish?: bool,
     *              materials: list<array{material_lot_id: int, quantity_used?: ?string}>} $data
     */
    public function createProductListing(User $artisan, array $data): Listing
    {
        return DB::transaction(function () use ($artisan, $data) {
            $lots = $this->ownedLots($artisan, $data['materials']);

            $listing = Listing::create($this->baseAttributes($artisan, ListingType::FinishedProduct, $data));
            $listing->productDetail()->create([
                'product_category' => $data['product_category'],
                'product_condition' => $data['product_condition'],
                'production_quantity' => (int) $data['production_quantity'],
                'unit' => 'piece',
            ]);
            $this->syncMaterials($listing, $data['materials'], $lots);
            $this->inventory->recordInitialStock($listing->fresh('productDetail'), (string) $data['production_quantity'], $artisan);

            return $listing->fresh(['productDetail', 'productMaterials']);
        });
    }

    /** Editable fields only. Type, lot and stock are not changed here. */
    public function update(Listing $listing, array $data): Listing
    {
        return DB::transaction(function () use ($listing, $data) {
            $listing->update(array_intersect_key($data, array_flip(['title', 'description', 'price', 'location'])));

            if ($listing->isRawMaterial() && array_key_exists('minimum_order_quantity', $data)) {
                $listing->rawMaterialDetail?->update(['minimum_order_quantity' => $data['minimum_order_quantity']]);
            }
            if (! $listing->isRawMaterial()) {
                $listing->productDetail?->update(array_intersect_key($data, array_flip(['product_category', 'product_condition'])));
                if (isset($data['materials'])) {
                    $this->syncMaterials($listing, $data['materials'], $this->ownedLots($listing->seller, $data['materials']));
                }
            }

            return $listing->fresh();
        });
    }

    public function changeStatus(Listing $listing, ListingStatus $to): Listing
    {
        return DB::transaction(function () use ($listing, $to) {
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $from = $locked->status;

            if (! in_array($to->value, self::SELLER_TRANSITIONS[$from->value], true)) {
                throw new BusinessRuleException("A {$from->label()} listing can’t be changed to {$to->label()}.", 'status');
            }
            if ($to === ListingStatus::Active && Money::toMilli($this->inventory->availableQuantity($locked)) <= 0) {
                throw new BusinessRuleException('There is no stock to sell, so the listing can’t go live.', 'status');
            }

            $locked->status = $to;
            if ($to === ListingStatus::Active && $locked->published_at === null) {
                $locked->published_at = now();
            }
            $locked->save();

            return $locked;
        });
    }

    /** @param list<UploadedFile> $files */
    public function addImages(Listing $listing, array $files): void
    {
        $existing = $listing->images()->count();
        if ($existing + count($files) > self::MAX_IMAGES) {
            throw new BusinessRuleException('A listing can have up to '.self::MAX_IMAGES.' photos.', 'images');
        }

        foreach ($files as $i => $file) {
            $listing->images()->create([
                'path' => $file->store('listings/'.$listing->id, 'public'),
                'alt_text' => $listing->title,
                'sort_order' => $existing + $i,
            ]);
        }
    }

    public function removeImage(ListingImage $image): void
    {
        Storage::disk('public')->delete($image->path);
        $image->delete();
    }

    private function baseAttributes(User $seller, ListingType $type, array $data): array
    {
        $publish = (bool) ($data['publish'] ?? false);

        return [
            'seller_id' => $seller->id,
            'listing_type' => $type,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'price' => Money::fromCents(Money::toCents($data['price'])),
            'currency' => 'GHS',
            'status' => $publish ? ListingStatus::Active : ListingStatus::Draft,
            'location' => $data['location'] ?? null,
            'published_at' => $publish ? now() : null,
        ];
    }

    private function lotIsListed(MaterialLot $lot): bool
    {
        return DB::table('raw_material_listing_details as d')
            ->join('listings as l', 'l.id', '=', 'd.listing_id')
            ->where('d.material_lot_id', $lot->id)
            ->whereNull('l.deleted_at')
            ->whereIn('l.status', array_map(fn ($s) => $s->value, ListingStatus::holdingStock()))
            ->exists();
    }

    /** @return \Illuminate\Support\Collection<int, MaterialLot> */
    private function ownedLots(User $owner, array $materials)
    {
        $ids = collect($materials)->pluck('material_lot_id')->map(fn ($id) => (int) $id)->unique();
        $lots = MaterialLot::whereIn('id', $ids)->where('owner_id', $owner->id)->get()->keyBy('id');

        if ($lots->count() !== $ids->count()) {
            throw new BusinessRuleException('Choose material lots that you own.', 'materials');
        }

        return $lots;
    }

    private function syncMaterials(Listing $listing, array $materials, $lots): void
    {
        $listing->productMaterials()->delete();
        foreach ($materials as $m) {
            $lot = $lots[(int) $m['material_lot_id']];
            $listing->productMaterials()->create([
                'material_lot_id' => $lot->id,
                'quantity_used' => isset($m['quantity_used']) && $m['quantity_used'] !== '' ? Money::quantity($m['quantity_used']) : null,
                'unit' => $lot->unit,
            ]);
        }
    }
}
