<?php

namespace App\Services;

use App\Enums\CollectionRecordStatus;
use App\Enums\CollectionStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\User;
use App\Notifications\CollectionUpdated;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Developer 2. Every change to a collection request or collection record goes
 * through here. Each method runs in a DB transaction and locks the row first,
 * so two collectors can't accept the same job and a status can't be skipped.
 * Controllers only call these methods; they never set status themselves.
 */
class CollectionService
{
    /** Hours a household has to confirm or dispute a record before it is confirmed for them. */
    public const AUTO_CONFIRM_AFTER_HOURS = 72;

    /**
     * @param  array{pickup_address: string, area?: ?string, gps_address?: ?string, latitude?: mixed, longitude?: mixed,
     *               contact_phone: string, preferred_date?: ?string, preferred_time_slot?: ?string, notes?: ?string,
     *               items: list<array{material_type: string, estimated_weight_kg?: mixed}>}  $data
     */
    public function createRequest(User $household, array $data): CollectionRequest
    {
        return DB::transaction(function () use ($household, $data) {
            $request = CollectionRequest::create([
                ...$this->requestFields($data),
                'household_id' => $household->id,
                'status' => CollectionStatus::Pending,
            ]);
            $this->syncItems($request, $data['items']);

            return $request->load('items');
        });
    }

    /** Households can change their request only while nobody has accepted it. */
    public function updateRequest(CollectionRequest $request, array $data): CollectionRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $request = $this->lock($request);
            if ($request->status !== CollectionStatus::Pending) {
                throw new BusinessRuleException('This request can no longer be changed because a collector has accepted it.');
            }
            $request->update($this->requestFields($data));
            $this->syncItems($request, $data['items']);

            return $request->load('items');
        });
    }

    /** A collector takes an open job. Only one collector can win. */
    public function accept(CollectionRequest $request, User $collector): CollectionRequest
    {
        return DB::transaction(function () use ($request, $collector) {
            $request = $this->lock($request);
            if ($request->status !== CollectionStatus::Pending || $request->collector_id !== null) {
                throw new BusinessRuleException('Another collector has already taken this job.');
            }
            $request->update([
                'status' => CollectionStatus::Assigned,
                'collector_id' => $collector->id,
                'assigned_by' => $collector->id,
                'assigned_at' => now(),
            ]);
            $this->notifyHousehold($request, 'Collector on the way', $collector->name.' accepted your pickup '.$request->reference.'.');

            return $request;
        });
    }

    /** An admin gives a pending or assigned job to a collector. */
    public function assign(CollectionRequest $request, User $collector, User $admin): CollectionRequest
    {
        if (! $collector->hasRole(RoleName::Collector)) {
            throw new BusinessRuleException('That user is not a collector.', 'collector_id');
        }

        return DB::transaction(function () use ($request, $collector, $admin) {
            $request = $this->lock($request);
            if (! in_array($request->status, [CollectionStatus::Pending, CollectionStatus::Assigned], true)) {
                throw new BusinessRuleException('Only requests that have not started can be assigned.', 'collector_id');
            }
            $request->update([
                'status' => CollectionStatus::Assigned,
                'collector_id' => $collector->id,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
            ]);
            $this->notifyHousehold($request, 'Collector assigned', $collector->name.' will collect pickup '.$request->reference.'.');

            return $request;
        });
    }

    /** The assigned collector gives the job back so others can take it. */
    public function release(CollectionRequest $request, User $collector): CollectionRequest
    {
        return DB::transaction(function () use ($request, $collector) {
            $request = $this->lockForCollector($request, $collector);
            $this->move($request, CollectionStatus::Pending, [
                'collector_id' => null, 'assigned_by' => null, 'assigned_at' => null,
            ]);
            $this->notifyHousehold($request, 'Looking for a new collector', 'Your collector could not make pickup '.$request->reference.'. Another collector can now take it.');

            return $request;
        });
    }

    /** The collector arrives and starts the pickup. */
    public function start(CollectionRequest $request, User $collector): CollectionRequest
    {
        return DB::transaction(function () use ($request, $collector) {
            $request = $this->lockForCollector($request, $collector);
            $this->move($request, CollectionStatus::InProgress, ['started_at' => now()]);

            return $request;
        });
    }

    /**
     * The collector weighs what was picked up and finishes the job. One record per
     * plastic type. The household then confirms or disputes each record.
     *
     * @param  list<array{material_type: string, actual_weight_kg: mixed}>  $lines
     */
    public function complete(CollectionRequest $request, User $collector, array $lines, ?string $notes = null): CollectionRequest
    {
        return DB::transaction(function () use ($request, $collector, $lines, $notes) {
            $request = $this->lockForCollector($request, $collector);
            if ($request->status !== CollectionStatus::InProgress) {
                throw new BusinessRuleException('Start the pickup before recording what you collected.');
            }
            $lines = $this->mergeLines($lines);
            if ($lines === []) {
                throw new BusinessRuleException('Add the weight of at least one plastic type.', 'lines');
            }

            foreach ($lines as $type => $milli) {
                CollectionRecord::create([
                    'collection_request_id' => $request->id,
                    'collector_id' => $collector->id,
                    'household_id' => $request->household_id,
                    'material_type' => $type,
                    'actual_weight_kg' => Money::quantity($milli / 1000),
                    'collected_at' => now(),
                    'status' => CollectionRecordStatus::PendingConfirmation,
                    'notes' => $notes,
                ]);
            }
            $this->move($request, CollectionStatus::Completed, ['completed_at' => now()]);

            $total = Money::quantity(array_sum($lines) / 1000);
            $this->notifyHousehold($request, 'Please confirm your pickup',
                'We collected '.$total.' kg for '.$request->reference.'. Please confirm the weights, or tell us if something is wrong.');

            return $request->load('records');
        });
    }

    /** Material dropped off without a request (walk-in). It is confirmed straight away. */
    public function recordWalkIn(User $collector, array $data): CollectionRecord
    {
        return CollectionRecord::create([
            'collector_id' => $collector->id,
            'material_type' => $data['material_type'],
            'actual_weight_kg' => Money::quantity($data['actual_weight_kg']),
            'collected_at' => isset($data['collected_at']) ? Carbon::parse($data['collected_at']) : now(),
            'status' => CollectionRecordStatus::Confirmed,
            'confirmed_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /** Cancel a request. Households can cancel until the pickup starts; admins until it is completed. */
    public function cancel(CollectionRequest $request, User $actor, ?string $reason = null): CollectionRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason) {
            $request = $this->lock($request);
            $isAdmin = $actor->hasRole(RoleName::Admin);
            if (! $isAdmin && $request->status === CollectionStatus::InProgress) {
                throw new BusinessRuleException('The collector has already started this pickup, so it can’t be cancelled now.');
            }
            $collectorId = $request->collector_id;
            $this->move($request, CollectionStatus::Cancelled, [
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ]);

            if ($collectorId && $collectorId !== $actor->id) {
                User::find($collectorId)?->notify(new CollectionUpdated($request, 'Pickup cancelled', 'Pickup '.$request->reference.' was cancelled.'));
            }
            if ($isAdmin) {
                $this->notifyHousehold($request, 'Pickup cancelled', 'Pickup '.$request->reference.' was cancelled by Reloop.'.($reason ? ' Reason: '.$reason : ''));
            }

            return $request;
        });
    }

    public function confirmRecord(CollectionRecord $record): CollectionRecord
    {
        return DB::transaction(function () use ($record) {
            $record = $this->lockRecord($record);
            $this->moveRecord($record, CollectionRecordStatus::Confirmed, ['confirmed_at' => now()]);

            return $record;
        });
    }

    public function disputeRecord(CollectionRecord $record, string $reason): CollectionRecord
    {
        return DB::transaction(function () use ($record, $reason) {
            $record = $this->lockRecord($record);
            $this->moveRecord($record, CollectionRecordStatus::Disputed, ['disputed_at' => now(), 'dispute_reason' => $reason]);
            $record->collector?->notify(new CollectionUpdated($record->collectionRequest, 'Weight disputed',
                'The household disputed record '.$record->reference.': '.$reason));

            return $record;
        });
    }

    /** An admin settles a dispute, optionally correcting the weight. */
    public function resolveDispute(CollectionRecord $record, ?string $correctedWeightKg, string $note): CollectionRecord
    {
        return DB::transaction(function () use ($record, $correctedWeightKg, $note) {
            $record = $this->lockRecord($record);
            if ($record->batchCollection()->exists() && $correctedWeightKg !== null) {
                throw new BusinessRuleException('This record is already in a batch, so its weight can’t be changed.', 'actual_weight_kg');
            }
            $changes = ['confirmed_at' => now(), 'notes' => trim(($record->notes ? $record->notes."\n" : '').'Dispute resolved: '.$note)];
            if ($correctedWeightKg !== null && $correctedWeightKg !== '') {
                $changes['actual_weight_kg'] = Money::quantity($correctedWeightKg);
            }
            $this->moveRecord($record, CollectionRecordStatus::Confirmed, $changes);

            return $record;
        });
    }

    /** Confirms records the household ignored. Run by ConfirmStaleCollectionRecords. Returns how many. */
    public function autoConfirmStale(): int
    {
        $count = 0;
        CollectionRecord::where('status', CollectionRecordStatus::PendingConfirmation->value)
            ->where('created_at', '<=', now()->subHours(self::AUTO_CONFIRM_AFTER_HOURS))
            ->orderBy('id')
            ->each(function (CollectionRecord $record) use (&$count) {
                try {
                    $this->confirmRecord($record);
                    $count++;
                } catch (BusinessRuleException) {
                    // changed by someone else meanwhile
                }
            });

        return $count;
    }

    // ---------------------------------------------------------------------

    private function requestFields(array $data): array
    {
        return [
            'pickup_address' => $data['pickup_address'],
            'area' => $data['area'] ?? null,
            'gps_address' => isset($data['gps_address']) ? strtoupper($data['gps_address']) : null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'contact_phone' => $data['contact_phone'],
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_time_slot' => $data['preferred_time_slot'] ?? null,
            'notes' => $data['notes'] ?? null,
            'estimated_weight_kg' => $this->estimatedTotal($data['items']),
        ];
    }

    private function estimatedTotal(array $items): ?string
    {
        $weights = array_filter(array_map(fn ($i) => $i['estimated_weight_kg'] ?? null, $items), fn ($w) => $w !== null && $w !== '');

        return $weights === [] ? null : Money::quantity(array_sum(array_map(fn ($w) => Money::toMilli($w), $weights)) / 1000);
    }

    private function syncItems(CollectionRequest $request, array $items): void
    {
        $request->items()->delete();
        foreach ($items as $item) {
            $request->items()->create([
                'material_type' => $item['material_type'],
                'estimated_weight_kg' => ($item['estimated_weight_kg'] ?? '') === '' ? null : Money::quantity($item['estimated_weight_kg']),
            ]);
        }
    }

    /** @return array<string, int> material type => weight in grams, lines with zero weight dropped */
    private function mergeLines(array $lines): array
    {
        $merged = [];
        foreach ($lines as $line) {
            $milli = Money::toMilli($line['actual_weight_kg']);
            if ($milli > 0) {
                $merged[$line['material_type']] = ($merged[$line['material_type']] ?? 0) + $milli;
            }
        }

        return $merged;
    }

    private function lock(CollectionRequest $request): CollectionRequest
    {
        return CollectionRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
    }

    private function lockForCollector(CollectionRequest $request, User $collector): CollectionRequest
    {
        $request = $this->lock($request);
        if ($request->collector_id !== $collector->id) {
            throw new BusinessRuleException('This job is not assigned to you.');
        }

        return $request;
    }

    private function lockRecord(CollectionRecord $record): CollectionRecord
    {
        return CollectionRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();
    }

    private function move(CollectionRequest $request, CollectionStatus $next, array $changes = []): void
    {
        if (! $request->status->canTransitionTo($next)) {
            throw new BusinessRuleException('This request is '.strtolower($request->status->label()).', so it can’t be changed that way.');
        }
        $request->update(['status' => $next, ...$changes]);
    }

    private function moveRecord(CollectionRecord $record, CollectionRecordStatus $next, array $changes = []): void
    {
        if (! $record->status->canTransitionTo($next)) {
            throw new BusinessRuleException('This record is already '.strtolower($record->status->label()).'.');
        }
        $record->update(['status' => $next, ...$changes]);
    }

    private function notifyHousehold(CollectionRequest $request, string $title, string $body): void
    {
        $request->household?->notify(new CollectionUpdated($request, $title, $body));
    }
}
