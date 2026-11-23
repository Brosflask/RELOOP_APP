<?php

namespace Database\Factories;

use App\Models\BatchCollection;
use App\Models\CollectionRecord;
use App\Models\MaterialBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 2.
 *
 * @extends Factory<BatchCollection>
 */
class BatchCollectionFactory extends Factory
{
    protected $model = BatchCollection::class;

    public function definition(): array
    {
        return [
            'material_batch_id' => MaterialBatch::factory(),
            'collection_record_id' => CollectionRecord::factory(),
            'weight_kg' => '10.000',
        ];
    }
}
