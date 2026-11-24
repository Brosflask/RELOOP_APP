<?php

namespace App\Http\Controllers\Collection;

use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CancelCollectionRequest;
use App\Models\CollectionRequest;
use App\Services\Collection\CollectionPresenter;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. The one detail page shared by the household, the collector and admins. */
class CollectionRequestController extends Controller
{
    public function show(Request $request, CollectionRequest $collectionRequest): Response
    {
        Gate::authorize('view', $collectionRequest);
        $user = $request->user();
        $collectionRequest->load(['items', 'household', 'collector', 'records.batchCollection']);
        $status = $collectionRequest->status;

        $isHousehold = $collectionRequest->household_id === $user->id;
        $isCollector = $user->can('work', $collectionRequest);
        $isAdmin = $user->can('assign', CollectionRequest::class);

        return Inertia::render('Collections/Show', [
            'collection' => CollectionPresenter::request($collectionRequest),
            'records' => $collectionRequest->records->map(fn ($r) => CollectionPresenter::record($r))->values(),
            'material_types' => MaterialType::options(),
            'can' => [
                'edit' => $isHousehold && $status === CollectionStatus::Pending,
                'cancel' => ($isHousehold && in_array($status, [CollectionStatus::Pending, CollectionStatus::Assigned], true))
                    || ($isAdmin && $status->isOpen()),
                'accept' => $user->can('accept', $collectionRequest) && $status === CollectionStatus::Pending && $collectionRequest->collector_id === null,
                'release' => $isCollector && $status === CollectionStatus::Assigned,
                'start' => $isCollector && $status === CollectionStatus::Assigned,
                'complete' => $isCollector && $status === CollectionStatus::InProgress,
                'respond' => $isHousehold && $collectionRequest->records->contains(fn ($r) => $r->status === CollectionRecordStatus::PendingConfirmation),
                // Contact details are only for the household, the assigned collector and admins.
                'see_contact' => $isHousehold || $isCollector || $isAdmin,
            ],
        ]);
    }

    public function cancel(CancelCollectionRequest $request, CollectionRequest $collectionRequest, CollectionService $collections): RedirectResponse
    {
        $collections->cancel($collectionRequest, $request->user(), $request->validated('reason'));

        return to_route('collections.show', $collectionRequest)->with('success', 'Pickup request cancelled.');
    }
}
