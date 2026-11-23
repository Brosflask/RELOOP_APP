<?php

namespace Database\Factories;

use App\Enums\CollectionRecordStatus;
use App\Enums\MaterialType;
use App\Models\CollectionRecord;
use App\Models\CollectionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 2. By default a walk-in record (no request) that is already confirmed,
 * so it can go straight into a batch. Use forRequest() for a household pickup.
 *
 * @extends Factory<CollectionRecord>
 */
class CollectionRecordFactory extends Factory
{
    protected $model = CollectionRecord::class;

    public function definition(): array
    {
        return [
            'collection_request_id' => null,
            'collector_id' => User::factory(),
            'household_id' => null,
            'material_type' => MaterialType::Pet,
            'actual_weight_kg' => '10.000',
            'collected_at' => now(),
            'status' => CollectionRecordStatus::Confirmed,
            'confirmed_at' => now(),
        ];
    }

    /** A record from a household's request, waiting for the household to confirm it. */
    public function forRequest(CollectionRequest $request): static
    {
        return $this->state(fn () => [
            'collection_request_id' => $request->id,
            'collector_id' => $request->collector_id ?? User::factory(),
            'household_id' => $request->household_id,
            'status' => CollectionRecordStatus::PendingConfirmation,
            'confirmed_at' => null,
        ]);
    }

    public function weighing(string $kg): static
    {
        return $this->state(fn () => ['actual_weight_kg' => $kg]);
    }

    public function disputed(): static
    {
        return $this->state(fn () => [
            'status' => CollectionRecordStatus::Disputed,
            'confirmed_at' => null,
            'disputed_at' => now(),
            'dispute_reason' => 'The weight looks too low.',
        ]);
    }
}
