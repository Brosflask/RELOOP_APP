<?php

namespace App\Services\Collection;

use App\Enums\BatchStatus;
use App\Enums\CollectionRecordStatus;
use App\Enums\MaterialType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\BatchCollection;
use App\Models\CollectionRecord;
use App\Models\MaterialBatch;
use App\Models\User;
use App\Notifications\BatchUpdated;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Developer 2. Collectors group confirmed collection records into batches and
 * send them to a recycler. Every method runs in a DB transaction with the rows
 * locked, so a record can never end up in two batches and totals always match.
 */
class BatchService
{
    /** @param  list<int>  $recordIds */
    public function create(User $collector, MaterialType $type, array $recordIds, ?string $notes = null): MaterialBatch
    {
        return DB::transaction(function () use ($collector, $type, $recordIds, $notes) {
            $batch = MaterialBatch::create([
                'collector_id' => $collector->id,
                'material_type' => $type,
                'status' => BatchStatus::Open,
                'total_weight_kg' => '0',
                'notes' => $notes,
            ]);
            $this->attach($batch, $collector, $recordIds);

            return $batch->refresh();
        });
    }

    /** @param  list<int>  $recordIds */
    public function addRecords(MaterialBatch $batch, User $collector, array $recordIds): MaterialBatch
    {
        return DB::transaction(function () use ($batch, $collector, $recordIds) {
            $batch = $this->lockOpen($batch, $collector);
            $this->attach($batch, $collector, $recordIds);

            return $batch->refresh();
        });
    }

    public function removeRecord(MaterialBatch $batch, User $collector, CollectionRecord $record): MaterialBatch
    {
        return DB::transaction(function () use ($batch, $collector, $record) {
            $batch = $this->lockOpen($batch, $collector);
            $deleted = BatchCollection::where('material_batch_id', $batch->id)->where('collection_record_id', $record->id)->delete();
            if ($deleted === 0) {
                throw new BusinessRuleException('That record is not in this batch.');
            }
            $this->recalculate($batch);

            return $batch->refresh();
        });
    }

    /**
     * Merge several open batches of one plastic type into a new, bigger batch.
     * The small batches keep their records (so the history stays exact) and point to the new one.
     *
     * @param  list<int>  $batchIds
     */
    public function consolidate(User $collector, array $batchIds, ?string $notes = null): MaterialBatch
    {
        return DB::transaction(function () use ($collector, $batchIds, $notes) {
            $batches = MaterialBatch::whereIn('id', $batchIds)->lockForUpdate()->orderBy('id')->get();
            if ($batches->count() < 2 || $batches->count() !== count(array_unique($batchIds))) {
                throw new BusinessRuleException('Choose at least two of your batches to merge.', 'batch_ids');
            }
            foreach ($batches as $b) {
                if ($b->collector_id !== $collector->id || $b->status !== BatchStatus::Open) {
                    throw new BusinessRuleException('Only your own open batches can be merged ('.$b->code.' is not).', 'batch_ids');
                }
            }
            if ($batches->pluck('material_type')->unique()->count() > 1) {
                throw new BusinessRuleException('All merged batches must be the same plastic type.', 'batch_ids');
            }

            $parent = MaterialBatch::create([
                'collector_id' => $collector->id,
                'material_type' => $batches->first()->material_type,
                'status' => BatchStatus::Open,
                'total_weight_kg' => '0',
                'notes' => $notes,
            ]);
            foreach ($batches as $b) {
                $b->update(['status' => BatchStatus::Consolidated, 'consolidated_into_id' => $parent->id]);
            }
            $this->recalculate($parent);

            return $parent->refresh();
        });
    }

    /** Send a batch to a recycler. After this it can't be changed. */
    public function dispatch(MaterialBatch $batch, User $collector, User $recycler, ?string $notes = null): MaterialBatch
    {
        if (! $recycler->hasRole(RoleName::Recycler)) {
            throw new BusinessRuleException('That user is not a recycling company.', 'recycler_id');
        }

        return DB::transaction(function () use ($batch, $collector, $recycler, $notes) {
            $batch = $this->lockOpen($batch, $collector);
            if (Money::toMilli((string) $batch->total_weight_kg) <= 0) {
                throw new BusinessRuleException('Add some collection records before sending this batch.');
            }
            $batch->update([
                'status' => BatchStatus::Dispatched,
                'recycler_id' => $recycler->id,
                'dispatched_at' => now(),
                'notes' => $notes ?? $batch->notes,
            ]);
            $recycler->notify(new BatchUpdated($batch, 'Batch on the way',
                $collector->name.' is sending you '.$batch->code.' ('.Money::quantity((string) $batch->total_weight_kg).' kg of '.$batch->material_type->label().').'));

            return $batch;
        });
    }

    // ---------------------------------------------------------------------

    /** @param  list<int>  $recordIds */
    private function attach(MaterialBatch $batch, User $collector, array $recordIds): void
    {
        $recordIds = array_values(array_unique(array_map('intval', $recordIds)));
        if ($recordIds === []) {
            throw new BusinessRuleException('Choose at least one collection record.', 'record_ids');
        }

        $records = CollectionRecord::whereIn('id', $recordIds)->lockForUpdate()->get()->keyBy('id');
        $alreadyBatched = BatchCollection::whereIn('collection_record_id', $recordIds)->pluck('collection_record_id')->all();

        foreach ($recordIds as $id) {
            $record = $records->get($id);
            if (! $record || $record->collector_id !== $collector->id) {
                throw new BusinessRuleException('You can only batch your own collection records.', 'record_ids');
            }
            if ($record->material_type !== $batch->material_type) {
                throw new BusinessRuleException($record->reference.' is '.$record->material_type->label().', but this batch is '.$batch->material_type->label().'.', 'record_ids');
            }
            if ($record->status !== CollectionRecordStatus::Confirmed) {
                throw new BusinessRuleException($record->reference.' is not confirmed yet, so it can’t be batched.', 'record_ids');
            }
            if (in_array($id, $alreadyBatched, true)) {
                throw new BusinessRuleException($record->reference.' is already in a batch.', 'record_ids');
            }
            BatchCollection::create([
                'material_batch_id' => $batch->id,
                'collection_record_id' => $record->id,
                'weight_kg' => $record->actual_weight_kg,
            ]);
        }
        $this->recalculate($batch);
    }

    /** Total = its own records + any batches merged into it. */
    private function recalculate(MaterialBatch $batch): void
    {
        $own = BatchCollection::where('material_batch_id', $batch->id)->pluck('weight_kg');
        $children = MaterialBatch::where('consolidated_into_id', $batch->id)->pluck('total_weight_kg');
        $milli = $own->concat($children)->sum(fn ($w) => Money::toMilli((string) $w));
        $batch->update(['total_weight_kg' => Money::quantity($milli / 1000)]);
    }

    private function lockOpen(MaterialBatch $batch, User $collector): MaterialBatch
    {
        $batch = MaterialBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
        if ($batch->collector_id !== $collector->id) {
            throw new BusinessRuleException('This batch is not yours.');
        }
        if (! $batch->status->isEditable()) {
            throw new BusinessRuleException('This batch is '.strtolower($batch->status->label()).', so it can’t be changed.');
        }

        return $batch;
    }
}
