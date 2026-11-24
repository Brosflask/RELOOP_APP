<?php

namespace App\Services\Recycling;

use App\Enums\MaterialLotStatus;
use App\Events\MaterialLotsCreated;
use App\Exceptions\BusinessRuleException;
use App\Models\MaterialLot;
use App\Models\ProcessingRecord;
use App\Models\User;
use App\Services\InventoryService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Developer 3. Records one processing run of a received batch and creates the
 * material lots it produced, each with its opening ledger row.
 * The loss is worked out here, never taken from the form.
 */
class ProcessingService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ReceivedBatches $batches,
    ) {
    }

    /**
     * @param  array{material_batch_id: int, process_type: string, input_weight_kg: string, processed_at?: string, notes?: ?string,
     *               lots: list<array{material_type: string, form?: ?string, grade?: ?string, quantity_kg: string}>}  $data
     */
    public function record(User $recycler, array $data): ProcessingRecord
    {
        return DB::transaction(function () use ($recycler, $data) {
            $batchId = (int) $data['material_batch_id'];
            DB::table('material_batches')->where('id', $batchId)->lockForUpdate()->first();

            $received = $this->batches->receivedWeight($recycler, $batchId);
            if ($received === null) {
                throw new BusinessRuleException('You can only process batches you have received.', 'material_batch_id');
            }

            $input = Money::toMilli($data['input_weight_kg']);
            $output = array_sum(array_map(fn ($lot) => Money::toMilli($lot['quantity_kg']), $data['lots']));
            $remaining = $this->batches->remainingMilli($batchId, $received);

            if ($input <= 0) {
                throw new BusinessRuleException('Input weight must be more than zero.', 'input_weight_kg');
            }
            if ($input > $remaining) {
                throw new BusinessRuleException('Only '.Money::quantity(max(0, $remaining) / 1000).' kg of this batch is left to process.', 'input_weight_kg');
            }
            if ($output <= 0) {
                throw new BusinessRuleException('Add at least one lot with a weight.', 'lots');
            }
            if ($output > $input) {
                throw new BusinessRuleException('The lots weigh more than the input. Output can’t be more than what went in.', 'lots');
            }

            $record = ProcessingRecord::create([
                'reference' => 'TMP-'.Str::uuid(),
                'recycler_id' => $recycler->id,
                'material_batch_id' => $batchId,
                'process_type' => $data['process_type'],
                'input_weight_kg' => Money::quantity($input / 1000),
                'output_weight_kg' => Money::quantity($output / 1000),
                'loss_weight_kg' => Money::quantity(($input - $output) / 1000),
                'processed_at' => isset($data['processed_at']) ? Carbon::parse($data['processed_at']) : now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $record->update(['reference' => sprintf('PR-%s-%06d', $record->created_at->format('Y'), $record->id)]);

            foreach ($data['lots'] as $lotData) {
                $lot = MaterialLot::create([
                    'code' => 'TMP-'.Str::uuid(),
                    'owner_id' => $recycler->id,
                    'processing_record_id' => $record->id,
                    'material_type' => $lotData['material_type'],
                    'form' => $lotData['form'] ?? null,
                    'grade' => $lotData['grade'] ?? null,
                    'initial_quantity' => Money::quantity($lotData['quantity_kg']),
                    'unit' => 'kg',
                    'status' => MaterialLotStatus::Available,
                ]);
                $lot->update(['code' => InventoryService::lotCode($lot)]);
                $this->inventory->recordProcessingOutput($lot, $recycler);
            }

            MaterialLotsCreated::dispatch($record);

            return $record->load('materialLots');
        });
    }
}
