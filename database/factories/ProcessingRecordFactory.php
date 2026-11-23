<?php

namespace Database\Factories;

use App\Models\MaterialBatch;
use App\Models\ProcessingRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Developer 3. Needs Developer 2's MaterialBatchFactory.
 *
 * @extends Factory<ProcessingRecord>
 */
class ProcessingRecordFactory extends Factory
{
    protected $model = ProcessingRecord::class;

    public function definition(): array
    {
        return [
            'reference' => 'PR-TEST-'.Str::upper(Str::random(8)),
            'recycler_id' => User::factory(),
            'material_batch_id' => MaterialBatch::factory(),
            'process_type' => 'shredding',
            'input_weight_kg' => '600.000',
            'output_weight_kg' => '540.000',
            'loss_weight_kg' => '60.000',
            'processed_at' => now(),
        ];
    }
}
