<?php

namespace App\Http\Controllers\Collection;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\DisputeCollectionRecordRequest;
use App\Models\CollectionRecord;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Developer 2. The household confirms or disputes what was weighed. */
class CollectionRecordResponseController extends Controller
{
    public function confirm(CollectionRecord $record, CollectionService $collections): RedirectResponse
    {
        Gate::authorize('respond', $record);
        $collections->confirmRecord($record);

        return back()->with('success', 'Thanks, weight confirmed.');
    }

    public function dispute(DisputeCollectionRecordRequest $request, CollectionRecord $record, CollectionService $collections): RedirectResponse
    {
        $collections->disputeRecord($record, $request->validated('reason'));

        return back()->with('success', 'Thanks. We’ve told the collector and an admin will check it.');
    }
}
