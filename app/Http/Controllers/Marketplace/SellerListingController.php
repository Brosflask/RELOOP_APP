<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Enums\MaterialLotStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreProductListingRequest;
use App\Http\Requests\Marketplace\StoreRawMaterialListingRequest;
use App\Http\Requests\Marketplace\UpdateListingRequest;
use App\Http\Requests\Marketplace\UpdateListingStatusRequest;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Services\InventoryService;
use App\Services\ListingService;
use App\Services\Marketplace\ListingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 3. A seller's own listings: create, edit, publish, pause, close. */
class SellerListingController extends Controller
{
    public function __construct(
        private readonly ListingService $listings,
        private readonly InventoryService $inventory,
    ) {
    }

    public function index(Request $request): Response
    {
        $status = $request->validate(['status' => ['nullable', Rule::enum(ListingStatus::class)]])['status'] ?? null;

        $listings = Listing::where('seller_id', $request->user()->id)
            ->with(['images', 'rawMaterialDetail', 'productDetail'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Listing $l) => ListingPresenter::card($l));

        return Inertia::render('Listings/Index', [
            'listings' => $listings,
            'filters' => ['status' => $status],
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], ListingStatus::cases()),
            'can' => [
                'create_raw_material' => $request->user()->can('createRawMaterial', Listing::class),
                'create_product' => $request->user()->can('createProduct', Listing::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $type = $request->query('type') === ListingType::FinishedProduct->value ? ListingType::FinishedProduct : ListingType::RawMaterial;
        Gate::authorize($type === ListingType::RawMaterial ? 'createRawMaterial' : 'createProduct', Listing::class);

        return Inertia::render('Listings/Create', [
            'type' => $type->value,
            'lots' => $this->lotOptions($request, $type === ListingType::RawMaterial),
            'selected_lot_id' => $request->integer('lot') ?: null,
            'categories' => ListingPresenter::categoryOptions(),
            'conditions' => ListingPresenter::options(StoreProductListingRequest::CONDITIONS),
        ]);
    }

    public function storeRawMaterial(StoreRawMaterialListingRequest $request): RedirectResponse
    {
        $listing = $this->listings->createRawMaterialListing($request->user(), $request->validated());

        return to_route('listings.edit', $listing)->with('success', $listing->status === ListingStatus::Active ? 'Listing published.' : 'Draft saved. Add photos, then publish it.');
    }

    public function storeProduct(StoreProductListingRequest $request): RedirectResponse
    {
        $listing = $this->listings->createProductListing($request->user(), $request->validated());

        return to_route('listings.edit', $listing)->with('success', $listing->status === ListingStatus::Active ? 'Listing published.' : 'Draft saved. Add photos, then publish it.');
    }

    public function edit(Request $request, Listing $listing): Response
    {
        Gate::authorize('update', $listing);
        $listing->load(['images', 'rawMaterialDetail.materialLot', 'productDetail', 'productMaterials']);
        $raw = $listing->isRawMaterial();

        return Inertia::render('Listings/Edit', [
            'listing' => ListingPresenter::card($listing) + [
                'description' => $listing->description,
                'available_quantity' => $this->inventory->availableQuantity($listing),
                'minimum_order_quantity' => $raw ? $listing->rawMaterialDetail?->minimum_order_quantity : null,
                'lot' => $raw && $listing->rawMaterialDetail ? ['id' => $listing->rawMaterialDetail->material_lot_id, 'code' => $listing->rawMaterialDetail->materialLot->code] : null,
                'product_category' => $listing->productDetail?->product_category,
                'product_condition' => $listing->productDetail?->product_condition,
                'materials' => $listing->productMaterials->map(fn ($pm) => [
                    'material_lot_id' => $pm->material_lot_id,
                    'quantity_used' => $pm->quantity_used ? (string) $pm->quantity_used : '',
                ])->values(),
                'images' => $listing->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url])->values(),
            ],
            'lots' => $raw ? [] : $this->lotOptions($request, false),
            'categories' => ListingPresenter::categoryOptions(),
            'conditions' => ListingPresenter::options(StoreProductListingRequest::CONDITIONS),
            'max_images' => ListingService::MAX_IMAGES,
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->listings->update($listing, $request->validated());

        return back()->with('success', 'Listing saved.');
    }

    public function updateStatus(UpdateListingStatusRequest $request, Listing $listing): RedirectResponse
    {
        $to = ListingStatus::from($request->validated('status'));
        $this->listings->changeStatus($listing, $to);

        return back()->with('success', match ($to) {
            ListingStatus::Active => 'Listing is live.',
            ListingStatus::Paused => 'Listing paused. Buyers can’t order until you resume it.',
            default => 'Listing closed.',
        });
    }

    /** Lots the user owns with stock left. For raw listings, only lots not already listed. */
    private function lotOptions(Request $request, bool $unlistedOnly): array
    {
        $lots = MaterialLot::ownedBy($request->user())
            ->where('status', MaterialLotStatus::Available->value)
            ->when($unlistedOnly, fn ($q) => $q->whereDoesntHave('rawMaterialListingDetail.listing', fn ($l) => $l
                ->whereIn('status', array_map(fn ($s) => $s->value, ListingStatus::holdingStock()))))
            ->latest('id')
            ->get();
        $available = $this->inventory->availableForLots($lots->pluck('id')->all());

        return $lots->map(fn (MaterialLot $lot) => [
            'id' => $lot->id,
            'code' => $lot->code,
            'label' => $lot->code.' · '.$lot->material_type->label().($lot->form ? ', '.$lot->form : '').' · '.(float) $available[$lot->id].' '.$lot->unit,
            'material_type' => $lot->material_type->value,
            'available_quantity' => $available[$lot->id],
            'unit' => $lot->unit,
        ])->values()->all();
    }
}
