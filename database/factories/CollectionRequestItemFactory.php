<?php

namespace Database\Factories;

use App\Enums\MaterialType;
use App\Models\CollectionRequest;
use App\Models\CollectionRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 2.
 *
 * @extends Factory<CollectionRequestItem>
 */
class CollectionRequestItemFactory extends Factory
{
    protected $model = CollectionRequestItem::class;

    public function definition(): array
    {
        return [
            'collection_request_id' => CollectionRequest::factory(),
            'material_type' => MaterialType::Pet,
            'estimated_weight_kg' => '5.000',
        ];
    }
}
