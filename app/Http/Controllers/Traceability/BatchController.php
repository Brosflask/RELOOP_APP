<?php

namespace App\Http\Controllers\Traceability;

use App\Enums\BatchStatus;
use App\Enums\CollectionRecordStatus;
use App\Enums\MaterialType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Traceability\AddBatchRecordsRequest;
use App\Http\Requests\Traceability\ConsolidateBatchesRequest;
use App\Http\Requests\Traceability\DispatchBatchRequest;
use App\Http\Requests\Traceability\StoreBatchRequest;
use App\Models\CollectionRecord;
use App\Models\MaterialBatch;
use App\Models\User;
use App\Services\Collection\BatchPresenter;
use App\Services\Collection\BatchService;
use App\Services\Collection\CollectionPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. Collectors build batches from confirmed records and send them to recyclers. */
class BatchController extends Controller
{
    public function __construct(private BatchService $batches) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::enum(BatchStatus::class)]]);

        $batches = MaterialBatch::where('collector_id', $request->user()->id)
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->with(['recycler', 'consolidatedInto'])
            ->withCount('batchCollections')
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MaterialBatch $b) => BatchPresenter::batch($b));

        return Inertia::render('Collector/Batches/Index', [
            'batches' => $batches,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => BatchStatus::options(),
            'ready' => $this->readyByType($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', MaterialBatch::class);
        $type = $request->enum('material_type', MaterialType::class);

        return Inertia::render('Collector/Batches/Create', [
            'material_types' => MaterialType::options(),
            'selected_type' => $type?->value,
            'records' => $type ? $this->eligibleRecords($request->user(), $type) : [],
            'ready' => $this->readyByType($request->user()),
        ]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $batch = $this->batches->create($request->user(), MaterialType::from($data['material_type']), $data['record_ids'], $data['notes'] ?? null);

        return to_route('batches.show', $batch)->with('success', 'Batch '.$batch->code.' created.');
    }

    public function show(Request $request, MaterialBatch $batch): Response
    {
        Gate::authorize('view', $batch);
        $user = $request->user();
        $batch->load(['collector', 'recycler', 'consolidatedInto', 'receipt', 'consolidatedBatches', 'collectionRecords']);
        $canManage = $user->can('manage', $batch) && $batch->status->isEditable();

        return Inertia::render('Batches/Show', [
            'batch' => BatchPresenter::batch($batch),
            'records' => $batch->collectionRecords->sortBy('collected_at')->map(fn ($r) => CollectionPresenter::record($r) + [
                'batched_weight_kg' => (string) $r->pivot->weight_kg,
            ])->values(),
            'merged_batches' => $batch->consolidatedBatches->map(fn ($b) => [
                'id' => $b->id, 'code' => $b->code, 'total_weight_kg' => (string) $b->total_weight_kg,
            ])->values(),
            'receipt' => $batch->receipt ? [
                'received_weight_kg' => (string) $batch->receipt->received_weight_kg,
                'expected_weight_kg' => $batch->receipt->expected_weight_kg !== null ? (string) $batch->receipt->expected_weight_kg : null,
                'weight_difference_kg' => $batch->receipt->weight_difference_kg !== null ? (string) $batch->receipt->weight_difference_kg : null,
                'received_at' => $batch->receipt->received_at?->toIso8601String(),
                'condition_notes' => $batch->receipt->condition_notes,
            ] : null,
            // Only loaded when the collector can still add records.
            'addable' => $canManage ? $this->eligibleRecords($user, $batch->material_type) : [],
            'recyclers' => $canManage ? $this->recyclers() : [],
            'can' => [
                'manage' => $canManage,
                'dispatch' => $canManage && (float) $batch->total_weight_kg > 0,
                'receive' => $user->can('receive', $batch) && $batch->status === BatchStatus::Dispatched,
                'reopen' => $user->can('manage', $batch) && $batch->status === BatchStatus::Rejected,
                'process' => $user->can('receive', $batch) && $batch->status === BatchStatus::Received,
            ],
        ]);
    }

    public function addRecords(AddBatchRecordsRequest $request, MaterialBatch $batch): RedirectResponse
    {
        $this->batches->addRecords($batch, $request->user(), $request->validated('record_ids'));

        return back()->with('success', 'Records added.');
    }

    public function removeRecord(Request $request, MaterialBatch $batch, CollectionRecord $record): RedirectResponse
    {
        Gate::authorize('manage', $batch);
        $this->batches->removeRecord($batch, $request->user(), $record);

        return back()->with('success', $record->reference.' taken out of the batch.');
    }

    public function consolidate(ConsolidateBatchesRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parent = $this->batches->consolidate($request->user(), $data['batch_ids'], $data['notes'] ?? null);

        return to_route('batches.show', $parent)->with('success', 'Batches merged into '.$parent->code.'.');
    }

    public function dispatch(DispatchBatchRequest $request, MaterialBatch $batch): RedirectResponse
    {
        $recycler = User::findOrFail($request->validated('recycler_id'));
        $this->batches->dispatch($batch, $request->user(), $recycler, $request->validated('notes'));

        return to_route('batches.show', $batch)->with('success', 'Batch sent to '.$recycler->name.'.');
    }

    public function reopen(Request $request, MaterialBatch $batch): RedirectResponse
    {
        Gate::authorize('manage', $batch);
        $this->batches->reopen($batch, $request->user());

        return to_route('batches.show', $batch)->with('success', 'Batch opened again. Fix it and send it again.');
    }

    // ---------------------------------------------------------------------

    /** Confirmed records of this type that are in no batch yet. */
    private function eligibleRecords(User $collector, MaterialType $type): array
    {
        return CollectionRecord::where('collector_id', $collector->id)
            ->where('material_type', $type->value)
            ->where('status', CollectionRecordStatus::Confirmed->value)
            ->unbatched()
            ->oldest('collected_at')
            ->limit(500)
            ->get()
            ->map(fn ($r) => CollectionPresenter::record($r))
            ->all();
    }

    /** kg ready to batch for each plastic type, for the "start a batch" shortcuts. */
    private function readyByType(User $collector): array
    {
        return CollectionRecord::where('collector_id', $collector->id)
            ->where('status', CollectionRecordStatus::Confirmed->value)
            ->unbatched()
            ->selectRaw('material_type, count(*) as records, sum(actual_weight_kg) as kg')
            ->groupBy('material_type')
            ->orderBy('material_type')
            ->get()
            ->map(fn ($row) => [
                'material_type' => $row->material_type->value,
                'material_label' => $row->material_type->label(),
                'records' => (int) $row->records,
                'kg' => number_format((float) $row->kg, 3, '.', ''),
            ])
            ->all();
    }

    private function recyclers(): array
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', RoleName::Recycler->value))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all();
    }
}
