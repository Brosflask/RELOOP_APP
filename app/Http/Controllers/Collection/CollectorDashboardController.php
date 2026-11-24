<?php

namespace App\Http\Controllers\Collection;

use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Http\Controllers\Controller;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Services\Collection\CollectionPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. A collector's jobs: open pickups to take, and the ones they are working on. */
class CollectorDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $collector = $request->user();
        $area = $request->string('area')->trim()->value() ?: null;

        $available = CollectionRequest::unassigned()
            ->where('household_id', '!=', $collector->id)
            ->when($area, fn ($q) => $q->where('area', $area))
            ->with('items')
            ->orderByRaw('preferred_date is null, preferred_date')
            ->oldest()
            ->limit(30)
            ->get()
            ->map(fn ($r) => CollectionPresenter::request($r));

        $mine = CollectionRequest::where('collector_id', $collector->id)
            ->whereIn('status', [CollectionStatus::Assigned->value, CollectionStatus::InProgress->value])
            ->with(['items', 'household'])
            ->orderByRaw('preferred_date is null, preferred_date')
            ->get()
            ->map(fn ($r) => CollectionPresenter::request($r));

        $records = CollectionRecord::where('collector_id', $collector->id);

        return Inertia::render('Collector/Dashboard', [
            'available' => $available,
            'mine' => $mine,
            'areas' => CollectionRequest::unassigned()->whereNotNull('area')->distinct()->orderBy('area')->pluck('area'),
            'filters' => ['area' => $area],
            'stats' => [
                'kg_this_month' => number_format((float) (clone $records)->where('collected_at', '>=', now()->startOfMonth())->sum('actual_weight_kg'), 3, '.', ''),
                'awaiting_confirmation' => (clone $records)->where('status', CollectionRecordStatus::PendingConfirmation->value)->count(),
                'disputed' => (clone $records)->where('status', CollectionRecordStatus::Disputed->value)->count(),
                'unbatched_kg' => number_format((float) (clone $records)->where('status', CollectionRecordStatus::Confirmed->value)
                    ->unbatched()->sum('actual_weight_kg'), 3, '.', ''),
            ],
        ]);
    }
}
