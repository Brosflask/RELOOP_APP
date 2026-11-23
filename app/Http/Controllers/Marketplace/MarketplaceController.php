<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreProductListingRequest;
use App\Models\Listing;
use App\Services\InventoryService;
use App\Services\Marketplace\ListingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 3. Public marketplace: browse, search, filter, listing page. */
class MarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', Rule::enum(ListingType::class)],
            'material_type' => ['nullable', Rule::enum(MaterialType::class)],
            'category' => ['nullable', Rule::in(StoreProductListingRequest::CATEGORIES)],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc'])],
        ]);

        $listings = Listing::query()
            ->where('status', ListingStatus::Active->value)
            ->with(['images', 'rawMaterialDetail', 'productDetail', 'seller:id,name'])
            ->when($filters['q'] ?? null, function (Builder $q, string $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('listing_type', $t))
            ->when($filters['material_type'] ?? null, fn ($q, $m) => $q->where(fn ($w) => $w
                ->whereHas('rawMaterialDetail', fn ($d) => $d->where('material_type', $m))
                ->orWhereHas('sourceLots', fn ($l) => $l->where('material_type', $m))))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->whereHas('productDetail', fn ($d) => $d->where('product_category', $c)))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('price', '<=', $filters['max_price']))
            ->when($filters['location'] ?? null, fn ($q, $l) => $q->where('location', 'like', '%'.$l.'%'))
            ->when(($filters['sort'] ?? 'newest') === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when(($filters['sort'] ?? 'newest') === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Listing $l) => ListingPresenter::card($l));

        return Inertia::render('Marketplace/Index', [
            'listings' => $listings,
            'filters' => array_merge(array_fill_keys(['q', 'type', 'material_type', 'category', 'min_price', 'max_price', 'location', 'sort'], null), $filters),
            'types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], ListingType::cases()),
            'material_types' => MaterialType::options(),
            'categories' => ListingPresenter::categoryOptions(),
        ]);
    }

    public function show(Request $request, Listing $listing, InventoryService $inventory): Response
    {
        Gate::authorize('view', $listing);
        $listing->load(['images', 'seller:id,name', 'rawMaterialDetail.materialLot.processingRecord', 'rawMaterialDetail.materialLot.parentLot.processingRecord',
            'productDetail', 'productMaterials.materialLot.processingRecord', 'productMaterials.materialLot.parentLot.processingRecord']);

        $user = $request->user();
        $isSeller = $user?->id === $listing->seller_id;
        $raw = $listing->isRawMaterial();

        return Inertia::render('Marketplace/Show', [
            'listing' => ListingPresenter::card($listing) + [
                'description' => $listing->description,
                'available_quantity' => $inventory->availableQuantity($listing),
                'minimum_order_quantity' => $raw && $listing->rawMaterialDetail?->minimum_order_quantity
                    ? (string) $listing->rawMaterialDetail->minimum_order_quantity : null,
                'product_condition' => $listing->productDetail?->product_condition,
                'images' => $listing->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url, 'alt_text' => $i->alt_text])->values(),
            ],
            'traceability' => $raw
                ? array_values(array_filter([$listing->rawMaterialDetail?->materialLot ? ListingPresenter::origin($listing->rawMaterialDetail->materialLot) : null]))
                : $listing->productMaterials->map(fn ($pm) => ListingPresenter::origin($pm->materialLot) + [
                    'quantity_used' => $pm->quantity_used ? (string) $pm->quantity_used : null,
                    'unit' => $pm->unit,
                ])->values(),
            'can' => [
                'buy' => $user !== null && ! $isSeller && $listing->status === ListingStatus::Active,
                'message' => $user !== null && ! $isSeller,
                'edit' => $user?->can('update', $listing) ?? false,
            ],
        ]);
    }
}
