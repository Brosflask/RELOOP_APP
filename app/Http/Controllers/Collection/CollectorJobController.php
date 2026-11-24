<?php

namespace App\Http\Controllers\Collection;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\CompleteCollectionRequest;
use App\Models\CollectionRequest;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Developer 2. A collector accepts, gives back, starts and completes a pickup. */
class CollectorJobController extends Controller
{
    public function __construct(private CollectionService $collections) {}

    public function accept(Request $request, CollectionRequest $collectionRequest): RedirectResponse
    {
        Gate::authorize('accept', $collectionRequest);
        $this->collections->accept($collectionRequest, $request->user());

        return to_route('collections.show', $collectionRequest)->with('success', 'Job accepted. The household has been told.');
    }

    public function release(Request $request, CollectionRequest $collectionRequest): RedirectResponse
    {
        Gate::authorize('work', $collectionRequest);
        $this->collections->release($collectionRequest, $request->user());

        return to_route('collector.dashboard')->with('success', 'Job given back. Another collector can take it.');
    }

    public function start(Request $request, CollectionRequest $collectionRequest): RedirectResponse
    {
        Gate::authorize('work', $collectionRequest);
        $this->collections->start($collectionRequest, $request->user());

        return back()->with('success', 'Pickup started. Weigh each type of plastic, then complete the job.');
    }

    public function complete(CompleteCollectionRequest $request, CollectionRequest $collectionRequest): RedirectResponse
    {
        $this->collections->complete($collectionRequest, $request->user(), $request->validated('lines'), $request->validated('notes'));

        return to_route('collections.show', $collectionRequest)->with('success', 'Pickup completed. The household will confirm the weights.');
    }
}
