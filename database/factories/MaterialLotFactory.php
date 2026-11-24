<?php

namespace Database\Factories;

use App\Enums\MaterialLotStatus;
use App\Enums\MaterialType;
use App\Models\MaterialLot;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Developer 3. Plain lots have no ledger rows (no stock); use withStock().
 *
 * @extends Factory<MaterialLot>
 */
class MaterialLotFactory extends Factory
{
    protected $model = MaterialLot::class;

    public function definition(): array
    {
        return [
            'code' => 'LOT-TEST-'.Str::upper(Str::random(8)),
            'owner_id' => User::factory(),
            'processing_record_id' => null,
            'material_type' => MaterialType::Pet,
            'form' => 'flakes',
            'grade' => 'A',
            'initial_quantity' => '500.000',
            'unit' => 'kg',
            'status' => MaterialLotStatus::Available,
        ];
    }

    /** Writes the opening processing_output row so the lot really has $kg. */
    public function withStock(string $kg = '500'): static
    {
        return $this->state(['initial_quantity' => $kg])
            ->afterCreating(fn (MaterialLot $lot) => app(InventoryService::class)->recordProcessingOutput($lot));
    }
}
