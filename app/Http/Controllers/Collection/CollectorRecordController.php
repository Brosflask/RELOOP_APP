<?php

namespace App\Http\Controllers\Collection;

use App\Enums\CollectionRecordStatus;
use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\StoreWalkInRecordRequest;
use App\Models\CollectionRecord;
use App\Services\Collection\CollectionPresenter;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. Everything a collector has collected, plus walk-in drop-offs. */
class CollectorRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CollectionRecordStatus::class)],
            'material_type' => ['nullable', Rule::enum(MaterialType::class)],
        ]);

        $records = CollectionRecord::where('collector_id', $request->user()->id)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['material_type'] ?? null, fn ($q, $t) => $q->where('material_type', $t))
            ->with('batchCollection')
            ->latest('collected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CollectionRecord $r) => CollectionPresenter::record($r));

        return Inertia::render('Collector/Records/Index', [
            'records' => $records,
            'filters' => ['status' => $filters['status'] ?? '', 'material_type' => $filters['material_type'] ?? ''],
            'statuses' => CollectionRecordStatus::options(),
            'material_types' => MaterialType::options(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', CollectionRecord::class);

        return Inertia::render('Collector/Records/Create', ['material_types' => MaterialType::options()]);
    }

    public function store(StoreWalkInRecordRequest $request, CollectionService $collections): RedirectResponse
    {
        $record = $collections->recordWalkIn($request->user(), $request->validated());

        return to_route('collector.records.index')->with('success', 'Recorded '.$record->reference.'.');
    }
}
