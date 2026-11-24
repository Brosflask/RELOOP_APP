<?php

namespace App\Http\Controllers\Traceability;

use App\Enums\BatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Traceability\ReceiveBatchRequest;
use App\Http\Requests\Traceability\RejectBatchRequest;
use App\Models\MaterialBatch;
use App\Services\Collection\BatchPresenter;
use App\Services\Collection\BatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. A recycler's incoming batches: confirm receipt or reject. */
class RecyclerBatchController extends Controller
{
    public function __construct(private BatchService $batches) {}

    public function index(Request $request): Response
    {
        $recycler = $request->user();
        $base = MaterialBatch::where('recycler_id', $recycler->id)->with(['collector', 'receipt'])->withCount('batchCollections');

        return Inertia::render('Recycler/Batches/Index', [
            'incoming' => (clone $base)->where('status', BatchStatus::Dispatched->value)->oldest('dispatched_at')->get()
                ->map(fn ($b) => BatchPresenter::batch($b)),
            'history' => (clone $base)->whereIn('status', [BatchStatus::Received->value, BatchStatus::Rejected->value])
                ->latest('updated_at')->paginate(20)->withQueryString()
                ->through(fn ($b) => BatchPresenter::batch($b) + [
                    'received_weight_kg' => $b->receipt ? (string) $b->receipt->received_weight_kg : null,
                ]),
        ]);
    }

    public function receive(ReceiveBatchRequest $request, MaterialBatch $batch): RedirectResponse
    {
        $data = $request->validated();
        $receipt = $this->batches->receive($batch, $request->user(), (string) $data['received_weight_kg'], $data['condition_notes'] ?? null);

        return to_route('batches.show', $batch)->with('success', 'Batch received ('.$receipt->received_weight_kg.' kg). You can now record its processing.');
    }

    public function reject(RejectBatchRequest $request, MaterialBatch $batch): RedirectResponse
    {
        $this->batches->reject($batch, $request->user(), $request->validated('reason'));

        return to_route('recycler.batches.index')->with('success', 'Batch rejected. The collector has been told.');
    }
}
