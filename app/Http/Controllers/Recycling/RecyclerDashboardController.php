<?php

namespace App\Http\Controllers\Recycling;

use App\Enums\MaterialLotStatus;
use App\Http\Controllers\Controller;
use App\Models\MaterialLot;
use App\Models\ProcessingRecord;
use App\Services\InventoryService;
use App\Services\Orders\OrderSummary;
use App\Services\Recycling\LotPresenter;
use App\Services\Recycling\ReceivedBatches;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Developer 3. */
class RecyclerDashboardController extends Controller
{
    public function __invoke(Request $request, InventoryService $inventory, ReceivedBatches $batches): Response
    {
        $user = $request->user();

        $lots = MaterialLot::ownedBy($user)->where('status', MaterialLotStatus::Available->value)->latest('id')->get();
        $available = $inventory->availableForLots($lots->pluck('id')->all());

        $month = ProcessingRecord::where('recycler_id', $user->id)
            ->where('processed_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(input_weight_kg), 0) AS input, COALESCE(SUM(loss_weight_kg), 0) AS loss')
            ->first();
        $inputMilli = Money::toMilli((string) $month->input);

        return Inertia::render('Recycler/Dashboard', [
            'stats' => [
                'lots_in_stock' => $lots->count(),
                'available_kg' => Money::quantity(array_sum(array_map(fn ($q) => Money::toMilli($q), $available)) / 1000),
                'processed_this_month_kg' => Money::quantity($inputMilli / 1000),
                'loss_rate_percent' => $inputMilli > 0 ? round(Money::toMilli((string) $month->loss) / $inputMilli * 100, 1) : null,
            ],
            'batches_waiting' => $batches->waitingFor($user)->take(5)->values(),
            'recent_records' => ProcessingRecord::where('recycler_id', $user->id)->latest('processed_at')->limit(5)->get()
                ->map(fn (ProcessingRecord $r) => [
                    'id' => $r->id,
                    'reference' => $r->reference,
                    'process_type' => $r->process_type,
                    'input_weight_kg' => (string) $r->input_weight_kg,
                    'output_weight_kg' => (string) $r->output_weight_kg,
                    'processed_at' => $r->processed_at->toIso8601String(),
                ]),
            'lots' => $lots->take(5)->map(fn ($lot) => LotPresenter::row($lot, $available))->values(),
            'orders' => OrderSummary::forSeller($user),
        ]);
    }
}
