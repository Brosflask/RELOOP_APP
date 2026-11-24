<?php

namespace App\Http\Controllers\Recycling;

use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recycling\StoreProcessingRecordRequest;
use App\Models\ProcessingRecord;
use App\Services\InventoryService;
use App\Services\Recycling\LotPresenter;
use App\Services\Recycling\ProcessingService;
use App\Services\Recycling\ReceivedBatches;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 3. */
class ProcessingRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $records = ProcessingRecord::where('recycler_id', $request->user()->id)
            ->withCount('materialLots')
            ->latest('processed_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProcessingRecord $r) => [
                'id' => $r->id,
                'reference' => $r->reference,
                'material_batch_id' => $r->material_batch_id,
                'process_type' => $r->process_type,
                'input_weight_kg' => (string) $r->input_weight_kg,
                'output_weight_kg' => (string) $r->output_weight_kg,
                'loss_weight_kg' => (string) $r->loss_weight_kg,
                'lots_count' => $r->material_lots_count,
                'processed_at' => $r->processed_at->toIso8601String(),
            ]);

        return Inertia::render('Recycler/Processing/Index', ['records' => $records]);
    }

    public function create(Request $request, ReceivedBatches $batches): Response
    {
        Gate::authorize('create', ProcessingRecord::class);

        return Inertia::render('Recycler/Processing/Create', [
            'batches' => $batches->waitingFor($request->user()),
            'selected_batch_id' => $request->integer('batch') ?: null,
            'process_types' => array_map(fn ($t) => ['value' => $t, 'label' => ucfirst($t)], StoreProcessingRecordRequest::PROCESS_TYPES),
            'forms' => array_map(fn ($f) => ['value' => $f, 'label' => ucfirst($f)], StoreProcessingRecordRequest::FORMS),
            'material_types' => MaterialType::options(),
        ]);
    }

    public function store(StoreProcessingRecordRequest $request, ProcessingService $processing): RedirectResponse
    {
        $record = $processing->record($request->user(), $request->validated());

        return to_route('recycler.processing.show', $record)
            ->with('success', 'Processing recorded. '.$record->materialLots->count().' lot(s) added to your stock.');
    }

    public function show(ProcessingRecord $record, InventoryService $inventory): Response
    {
        Gate::authorize('view', $record);
        $record->load('materialLots');
        $available = $inventory->availableForLots($record->materialLots->pluck('id')->all());

        return Inertia::render('Recycler/Processing/Show', [
            'record' => [
                'id' => $record->id,
                'reference' => $record->reference,
                'material_batch_id' => $record->material_batch_id,
                'process_type' => $record->process_type,
                'input_weight_kg' => (string) $record->input_weight_kg,
                'output_weight_kg' => (string) $record->output_weight_kg,
                'loss_weight_kg' => (string) $record->loss_weight_kg,
                'processed_at' => $record->processed_at->toIso8601String(),
                'notes' => $record->notes,
            ],
            'lots' => $record->materialLots->map(fn ($lot) => LotPresenter::row($lot, $available))->values(),
        ]);
    }
}
