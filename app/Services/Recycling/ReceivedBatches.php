<?php

namespace App\Services\Recycling;

use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Developer 3. Reads Developer 2's batch receipts (read-only) to find the
 * batches a recycler has received and how many kg are still unprocessed.
 *
 * ASSUMED COLUMNS, confirm with Developer 2 and change only the constants:
 * batch_receipts.material_batch_id, batch_receipts.recycler_id,
 * batch_receipts.received_weight_kg.
 */
class ReceivedBatches
{
    public const TABLE = 'batch_receipts';

    public const BATCH = 'material_batch_id';

    public const RECYCLER = 'recycler_id';

    public const WEIGHT = 'received_weight_kg';

    /** Batches this recycler received that still have kg left to process. */
    public function waitingFor(User $recycler): Collection
    {
        return DB::table(self::TABLE.' as r')
            ->join('material_batches as b', 'b.id', '=', 'r.'.self::BATCH)
            ->where('r.'.self::RECYCLER, $recycler->id)
            ->select(['b.id', 'r.'.self::WEIGHT.' as received_weight_kg', 'r.created_at as received_at'])
            ->orderByDesc('r.created_at')
            ->get()
            ->map(function ($row) {
                $remaining = $this->remainingMilli((int) $row->id, (string) $row->received_weight_kg);

                return [
                    'id' => (int) $row->id,
                    'received_weight_kg' => Money::quantity($row->received_weight_kg),
                    'remaining_weight_kg' => Money::quantity($remaining / 1000),
                    'received_at' => $row->received_at ? \Illuminate\Support\Carbon::parse($row->received_at)->toIso8601String() : null,
                ];
            })
            ->filter(fn ($b) => Money::toMilli($b['remaining_weight_kg']) > 0)
            ->values();
    }

    /** Received weight for this recycler and batch, or null when they never received it. */
    public function receivedWeight(User $recycler, int $batchId): ?string
    {
        $weight = DB::table(self::TABLE)
            ->where(self::BATCH, $batchId)
            ->where(self::RECYCLER, $recycler->id)
            ->sum(self::WEIGHT);
        $received = DB::table(self::TABLE)->where(self::BATCH, $batchId)->where(self::RECYCLER, $recycler->id)->exists();

        return $received ? Money::quantity($weight) : null;
    }

    /** kg of the batch not yet used as input to a processing record (thousandths). */
    public function remainingMilli(int $batchId, string $receivedWeight): int
    {
        $used = DB::table('processing_records')->where('material_batch_id', $batchId)->sum('input_weight_kg');

        return Money::toMilli($receivedWeight) - Money::toMilli((string) $used);
    }
}
