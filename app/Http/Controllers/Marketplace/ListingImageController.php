<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\StoreListingImagesRequest;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Services\ListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Developer 3. */
class ListingImageController extends Controller
{
    public function store(StoreListingImagesRequest $request, Listing $listing, ListingService $listings): RedirectResponse
    {
        $listings->addImages($listing, $request->file('images'));

        return back()->with('success', 'Photos added.');
    }

    public function destroy(Listing $listing, ListingImage $image, ListingService $listings): RedirectResponse
    {
        Gate::authorize('update', $listing);
        abort_unless($image->listing_id === $listing->id, 404);
        $listings->removeImage($image);

        return back()->with('success', 'Photo removed.');
    }
}
