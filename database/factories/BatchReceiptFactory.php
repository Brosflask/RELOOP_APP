<?php

namespace Database\Factories;

use App\Models\BatchReceipt;
use App\Models\MaterialBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 2.
 *
 * @extends Factory<BatchReceipt>
 */
class BatchReceiptFactory extends Factory
{
    protected $model = BatchReceipt::class;

    public function definition(): array
    {
        return [
            'material_batch_id' => MaterialBatch::factory(),
            'recycler_id' => User::factory(),
            'expected_weight_kg' => '600.000',
            'received_weight_kg' => '600.000',
            'weight_difference_kg' => '0.000',
            'received_at' => now(),
        ];
    }
}
