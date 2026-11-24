<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Enums\MaterialType;
use App\Models\BatchReceipt;
use App\Models\MaterialBatch;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 2. Used by Developer 3's ProcessingRecordFactory too.
 *
 *   MaterialBatch::factory()->create()                          open, 600 kg, no records
 *   MaterialBatch::factory()->dispatchedTo($recycler)->create() on its way
 *   MaterialBatch::factory()->receivedBy($recycler, '600')->create()  received, with a batch_receipts row
 *
 * @extends Factory<MaterialBatch>
 */
class MaterialBatchFactory extends Factory
{
    protected $model = MaterialBatch::class;

    public function definition(): array
    {
        return [
            'collector_id' => User::factory(),
            'material_type' => MaterialType::Pet,
            'total_weight_kg' => '600.000',
            'status' => BatchStatus::Open,
        ];
    }

    public function ofType(MaterialType $type): static
    {
        return $this->state(fn () => ['material_type' => $type]);
    }

    public function dispatchedTo(?User $recycler = null): static
    {
        return $this->state(fn () => [
            'status' => BatchStatus::Dispatched,
            'recycler_id' => $recycler?->id ?? User::factory(),
            'dispatched_at' => now(),
        ]);
    }

    /** Received by the recycler, with its batch_receipts row. Weight defaults to the batch total. */
    public function receivedBy(?User $recycler = null, ?string $receivedKg = null): static
    {
        return $this->dispatchedTo($recycler)
            ->state(fn () => ['status' => BatchStatus::Received, 'received_at' => now()])
            ->afterCreating(function (MaterialBatch $batch) use ($receivedKg) {
                $kg = $receivedKg ?? (string) $batch->total_weight_kg;
                BatchReceipt::factory()->create([
                    'material_batch_id' => $batch->id,
                    'recycler_id' => $batch->recycler_id,
                    'expected_weight_kg' => $batch->total_weight_kg,
                    'received_weight_kg' => $kg,
                    'weight_difference_kg' => Money::quantity((Money::toMilli($kg) - Money::toMilli((string) $batch->total_weight_kg)) / 1000),
                ]);
            });
    }
}
