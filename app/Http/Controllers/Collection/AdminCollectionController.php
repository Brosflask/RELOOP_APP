<?php

namespace App\Http\Controllers\Collection;

use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collection\AssignCollectorRequest;
use App\Http\Requests\Collection\ResolveDisputeRequest;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\User;
use App\Services\Collection\CollectionPresenter;
use App\Services\CollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 2. Admin view of every pickup: assign collectors and settle weight disputes. */
class AdminCollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CollectionStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $requests = CollectionRequest::query()
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('reference', 'like', "%{$s}%")->orWhere('area', 'like', "%{$s}%")->orWhere('pickup_address', 'like', "%{$s}%")))
            ->with(['items', 'household', 'collector'])
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (CollectionRequest $r) => CollectionPresenter::request($r));

        $counts = CollectionRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Admin/Collections/Index', [
            'requests' => $requests,
            'filters' => ['status' => $filters['status'] ?? '', 'search' => $filters['search'] ?? ''],
            'statuses' => CollectionStatus::options(),
            'counts' => collect(CollectionStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'collectors' => User::whereHas('roles', fn ($q) => $q->where('name', RoleName::Collector->value))
                ->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['value' => $u->id, 'label' => $u->name]),
            'disputes' => CollectionRecord::where('status', CollectionRecordStatus::Disputed->value)
                ->with(['collector', 'household'])->oldest('disputed_at')->limit(50)->get()
                ->map(fn ($r) => CollectionPresenter::record($r) + [
                    'collector_name' => $r->collector?->name,
                    'household_name' => $r->household?->name,
                ]),
        ]);
    }

    public function assign(AssignCollectorRequest $request, CollectionRequest $collectionRequest, CollectionService $collections): RedirectResponse
    {
        $collector = User::findOrFail($request->validated('collector_id'));
        $collections->assign($collectionRequest, $collector, $request->user());

        return back()->with('success', $collectionRequest->reference.' assigned to '.$collector->name.'.');
    }

    public function resolve(ResolveDisputeRequest $request, CollectionRecord $record, CollectionService $collections): RedirectResponse
    {
        $collections->resolveDispute($record, $request->validated('actual_weight_kg'), $request->validated('note'));

        return back()->with('success', 'Dispute on '.$record->reference.' resolved.');
    }
}
