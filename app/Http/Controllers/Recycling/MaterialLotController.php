<?php

namespace App\Http\Controllers\Recycling;

use App\Enums\MaterialLotStatus;
use App\Enums\MaterialType;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\MaterialLot;
use App\Services\InventoryService;
use App\Services\Recycling\LotPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 3. A user's own lots: produced (recyclers) or bought (artisans). */
class MaterialLotController extends Controller
{
    public function index(Request $request, InventoryService $inventory): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(MaterialLotStatus::class)],
            'material_type' => ['nullable', Rule::enum(MaterialType::class)],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $lots = MaterialLot::ownedBy($request->user())
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['material_type'] ?? null, fn ($q, $t) => $q->where('material_type', $t))
            ->when($filters['q'] ?? null, fn ($q, $s) => $q->where('code', 'like', '%'.$s.'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $available = $inventory->availableForLots($lots->pluck('id')->all());

        return Inertia::render('Inventory/Lots/Index', [
            'lots' => $lots->through(fn ($lot) => LotPresenter::row($lot, $available)),
            'filters' => [
                'status' => $filters['status'] ?? null,
                'material_type' => $filters['material_type'] ?? null,
                'q' => $filters['q'] ?? null,
            ],
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], MaterialLotStatus::cases()),
            'material_types' => MaterialType::options(),
        ]);
    }

    public function show(Request $request, MaterialLot $lot, InventoryService $inventory): Response
    {
        Gate::authorize('view', $lot);
        $lot->load(['processingRecord', 'parentLot', 'rawMaterialListingDetail.listing']);

        $history = $inventory->history($lot)->paginate(25)->withQueryString()
            ->through(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'movement_type' => $m->movement_type->value,
                'movement_label' => $m->movement_type->label(),
                'quantity_change' => (string) $m->quantity_change,
                'reserved_change' => (string) $m->reserved_change,
                'unit' => $m->unit,
                'note' => $m->note,
                'order_id' => $m->order_id,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        $listing = $lot->rawMaterialListingDetail?->listing;

        return Inertia::render('Inventory/Lots/Show', [
            'lot' => LotPresenter::row($lot, [$lot->id => $inventory->availableQuantityForLot($lot)]) + [
                'processing_record' => $lot->processingRecord && $lot->processingRecord->recycler_id === $request->user()->id
                    ? $lot->processingRecord->only('id', 'reference') : null,
                'parent_lot_code' => $lot->parentLot?->code,
                'listing' => $listing ? ['id' => $listing->id, 'title' => $listing->title, 'status' => $listing->status->value] : null,
            ],
            'balances' => $inventory->balances($lot),
            'history' => $history,
            'can' => ['adjust' => $request->user()->can('adjust', $lot)],
        ]);
    }
}
