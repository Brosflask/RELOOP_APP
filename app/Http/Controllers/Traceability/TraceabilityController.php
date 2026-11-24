<?php

namespace App\Http\Controllers\Traceability;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Services\TraceabilityService;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. "Where did this plastic come from?" pages. */
class TraceabilityController extends Controller
{
    public function __construct(private TraceabilityService $trace) {}

    /** Public, like the marketplace listing page. Only active (or sold out) listings. */
    public function listing(Listing $listing): Response
    {
        abort_unless(in_array($listing->status, [ListingStatus::Active, ListingStatus::SoldOut], true), 404);

        return Inertia::render('Traceability/Show', [
            'title' => $listing->title,
            'subtitle' => $listing->isRawMaterial() ? 'Recycled raw material' : 'Made from recycled plastic',
            'back' => ['label' => 'Back to listing', 'route' => 'marketplace.show', 'id' => $listing->id],
            'traces' => $this->trace->forListing($listing),
        ]);
    }

    /** Signed-in users: for lot pages and admin checks. */
    public function lot(MaterialLot $lot): Response
    {
        return Inertia::render('Traceability/Show', [
            'title' => $lot->code,
            'subtitle' => $lot->material_type->label().' lot',
            'back' => null,
            'traces' => [$this->trace->forLot($lot)],
        ]);
    }
}
