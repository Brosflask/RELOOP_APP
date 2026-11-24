<?php

namespace App\Services;

use App\Models\BatchCollection;
use App\Models\CollectionRecord;
use App\Models\Listing;
use App\Models\MaterialBatch;
use App\Models\MaterialLineage;
use App\Models\MaterialLot;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Developer 2. Follows a listing or a material lot back to the households'
 * pickups it came from:
 *
 *   listing -> lot(s) -> (parent lots, if bought from another seller) -> processing record
 *           -> batch(es) (+ batches merged into them) -> collection records -> pickup areas
 *
 * Read-only. Privacy: households are never named (only area and date), collectors
 * are shown by first name, businesses (recyclers, artisans) by name.
 */
class TraceabilityService
{
    private const MAX_PARENT_DEPTH = 10;

    private const MAX_RECORDS_SHOWN = 50;

    /** @return list<array> one trace per source lot */
    public function forListing(Listing $listing): array
    {
        $lotIds = $listing->isRawMaterial()
            ? array_filter([$listing->rawMaterialDetail?->material_lot_id])
            : $listing->productMaterials()->pluck('material_lot_id')->all();

        return MaterialLot::whereIn('id', $lotIds)->orderBy('id')->get()
            ->map(fn (MaterialLot $lot) => $this->forLot($lot))
            ->all();
    }

    public function forLot(MaterialLot $lot): array
    {
        // 1. Walk up from a bought lot to the lot the recycler produced.
        $chain = [$lot];
        $current = $lot;
        while ($current->parent_lot_id && count($chain) <= self::MAX_PARENT_DEPTH) {
            $current = MaterialLot::find($current->parent_lot_id);
            if (! $current) {
                break;
            }
            $chain[] = $current;
        }
        $origin = end($chain);
        $processing = $origin->processingRecord()->with('recycler')->first();

        // 2. Batches it was made from: lineage rows, or the processing record's batch.
        $batchIds = MaterialLineage::where('material_lot_id', $origin->id)->pluck('material_batch_id')->all();
        if ($batchIds === [] && $processing) {
            $batchIds = [$processing->material_batch_id];
        }

        return [
            'lot' => $this->lot($lot),
            'ownership' => array_map(fn (MaterialLot $l) => $this->lot($l), array_reverse($chain)),
            'processing' => $processing ? [
                'reference' => $processing->reference,
                'process_type' => $processing->process_type,
                'input_weight_kg' => (string) $processing->input_weight_kg,
                'output_weight_kg' => (string) $processing->output_weight_kg,
                'loss_weight_kg' => (string) $processing->loss_weight_kg,
                'processed_at' => $processing->processed_at?->toIso8601String(),
                'recycler' => $processing->recycler?->name,
            ] : null,
            'batches' => MaterialBatch::whereIn('id', $batchIds)->with(['collector', 'recycler', 'receipt'])->orderBy('id')->get()
                ->map(fn (MaterialBatch $b) => $this->batch($b))->all(),
        ];
    }

    private function lot(MaterialLot $lot): array
    {
        return [
            'id' => $lot->id,
            'code' => $lot->code,
            'material_label' => $lot->material_type->label(),
            'form' => $lot->form,
            'grade' => $lot->grade,
            'quantity' => (string) $lot->initial_quantity,
            'unit' => $lot->unit,
            'owner' => $lot->owner?->name,
            'created_at' => $lot->created_at->toIso8601String(),
        ];
    }

    private function batch(MaterialBatch $batch): array
    {
        $ids = $this->withMergedBatches($batch);
        $recordIds = BatchCollection::whereIn('material_batch_id', $ids)->pluck('collection_record_id');
        $records = CollectionRecord::whereIn('id', $recordIds)->with('collectionRequest:id,area')->orderBy('collected_at')->get();

        return [
            'code' => $batch->code,
            'material_label' => $batch->material_type->label(),
            'total_weight_kg' => (string) $batch->total_weight_kg,
            'collector' => self::firstName($batch->collector),
            'dispatched_at' => $batch->dispatched_at?->toIso8601String(),
            'received_at' => $batch->received_at?->toIso8601String(),
            'received_weight_kg' => $batch->receipt ? (string) $batch->receipt->received_weight_kg : null,
            'recycler' => $batch->recycler?->name,
            'merged_batches' => MaterialBatch::whereIn('id', array_diff($ids, [$batch->id]))->orderBy('id')->pluck('code')->all(),
            'collections' => $this->collections($records),
        ];
    }

    private function collections(Collection $records): array
    {
        $areas = $records->map(fn ($r) => $r->collectionRequest?->area)->filter()->unique()->sort()->values();

        return [
            'count' => $records->count(),
            'total_kg' => Money::quantity($records->sum(fn ($r) => Money::toMilli((string) $r->actual_weight_kg)) / 1000),
            'households' => $records->pluck('household_id')->filter()->unique()->count(),
            'drop_offs' => $records->whereNull('collection_request_id')->count(),
            'areas' => $areas->all(),
            'first_collected_at' => $records->first()?->collected_at?->toIso8601String(),
            'last_collected_at' => $records->last()?->collected_at?->toIso8601String(),
            'records' => $records->take(self::MAX_RECORDS_SHOWN)->map(fn ($r) => [
                'reference' => $r->reference,
                'weight_kg' => (string) $r->actual_weight_kg,
                'collected_at' => $r->collected_at->toIso8601String(),
                'area' => $r->collectionRequest?->area,
                'source' => $r->collection_request_id ? 'Household pickup' : 'Drop-off',
            ])->values()->all(),
        ];
    }

    /** The batch plus every batch merged into it (and merged into those). @return list<int> */
    private function withMergedBatches(MaterialBatch $batch): array
    {
        $ids = [$batch->id];
        $frontier = [$batch->id];
        for ($depth = 0; $frontier !== [] && $depth < self::MAX_PARENT_DEPTH; $depth++) {
            $frontier = MaterialBatch::whereIn('consolidated_into_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return array_values(array_unique($ids));
    }

    private static function firstName(?User $user): ?string
    {
        return $user ? strtok(trim($user->name), ' ') : null;
    }
}
