<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Services\InventoryService;
use App\Services\Marketplace\ListingPresenter;
use App\Services\Orders\OrderSummary;
use App\Services\Recycling\LotPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 3. */
class ArtisanDashboardController extends Controller
{
    public function __invoke(Request $request, InventoryService $inventory): Response
    {
        $user = $request->user();
        $products = Listing::where('seller_id', $user->id)->ofType(ListingType::FinishedProduct)
            ->with(['images', 'productDetail'])->latest('id')->limit(6)->get();
        $lots = MaterialLot::ownedBy($user)->latest('id')->limit(5)->get();
        $available = $inventory->availableForLots($lots->pluck('id')->all());

        return Inertia::render('Artisan/Dashboard', [
            'stats' => [
                'active_products' => Listing::where('seller_id', $user->id)->ofType(ListingType::FinishedProduct)->active()->count(),
                'draft_products' => Listing::where('seller_id', $user->id)->ofType(ListingType::FinishedProduct)->where('status', ListingStatus::Draft->value)->count(),
                'material_lots' => MaterialLot::ownedBy($user)->count(),
            ],
            'products' => $products->map(fn ($l) => ListingPresenter::card($l))->values(),
            'lots' => $lots->map(fn ($lot) => LotPresenter::row($lot, $available))->values(),
            'orders' => OrderSummary::forSeller($user),
        ]);
    }
}
