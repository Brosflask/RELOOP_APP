<?php

namespace App\Http\Controllers\Collection;

use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\StoreCollectionRequestRequest;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Services\Collection\CollectionPresenter;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. A household's own pickup requests. */
class HouseholdCollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $requests = CollectionRequest::where('household_id', $request->user()->id)
            ->with(['items', 'collector'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (CollectionRequest $r) => CollectionPresenter::request($r));

        return Inertia::render('Household/Collections/Index', [
            'requests' => $requests,
            'open_count' => CollectionRequest::where('household_id', $request->user()->id)->open()->count(),
            'kg_collected' => number_format((float) CollectionRecord::where('household_id', $request->user()->id)
                ->where('status', CollectionRecordStatus::Confirmed->value)->sum('actual_weight_kg'), 3, '.', ''),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CollectionRequest::class);
        $last = CollectionRequest::where('household_id', $request->user()->id)->latest()->first();

        return Inertia::render('Household/Collections/Form', [
            'collection' => null,
            // Prefill from the household's last request to save typing.
            'defaults' => $last ? [
                'pickup_address' => $last->pickup_address, 'area' => $last->area,
                'gps_address' => $last->gps_address, 'contact_phone' => $last->contact_phone,
            ] : null,
            ...$this->options(),
        ]);
    }

    public function store(StoreCollectionRequestRequest $request, CollectionService $collections): RedirectResponse
    {
        $created = $collections->createRequest($request->user(), $request->validated());

        return to_route('collections.show', $created)
            ->with('success', 'Pickup requested. We’ll let you know when a collector accepts it.');
    }

    public function edit(CollectionRequest $collectionRequest): Response|RedirectResponse
    {
        Gate::authorize('update', $collectionRequest);
        if ($collectionRequest->status !== CollectionStatus::Pending) {
            return to_route('collections.show', $collectionRequest)->with('error', 'This request can no longer be changed.');
        }

        return Inertia::render('Household/Collections/Form', [
            'collection' => CollectionPresenter::request($collectionRequest->load('items')),
            'defaults' => null,
            ...$this->options(),
        ]);
    }

    public function update(StoreCollectionRequestRequest $request, CollectionRequest $collectionRequest, CollectionService $collections): RedirectResponse
    {
        $collections->updateRequest($collectionRequest, $request->validated());

        return to_route('collections.show', $collectionRequest)->with('success', 'Pickup request updated.');
    }

    private function options(): array
    {
        return [
            'material_types' => MaterialType::options(),
            'time_slots' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], StoreCollectionRequestRequest::TIME_SLOTS),
        ];
    }
}
