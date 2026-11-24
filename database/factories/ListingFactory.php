<?php

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\ListingType;
use App\Models\Listing;
use App\Models\MaterialLot;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 3. Developer 4's tests use the plain factory; the states below
 * also create details and real stock.
 *
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    public function definition(): array
    {
        return [
            'seller_id' => User::factory(),
            'listing_type' => ListingType::FinishedProduct,
            'title' => ucfirst(fake()->words(3, true)),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(2, 5, 500),
            'currency' => 'GHS',
            'status' => ListingStatus::Active,
            'location' => fake()->randomElement(['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Cape Coast']),
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ListingStatus::Draft, 'published_at' => null]);
    }

    /** Raw material sold per kg from a lot. Pass a lot, or one is made for the seller with $kg in stock. */
    public function rawMaterial(?MaterialLot $lot = null, string $kg = '500'): static
    {
        return $this->state(fn () => [
            'listing_type' => ListingType::RawMaterial,
            'title' => 'Clean PET flakes',
            'price' => '3.20',
        ] + ($lot ? ['seller_id' => $lot->owner_id] : []))->afterCreating(function (Listing $listing) use ($lot, $kg) {
            $lot ??= MaterialLot::factory()->withStock($kg)->create(['owner_id' => $listing->seller_id]);
            $listing->rawMaterialDetail()->create([
                'material_lot_id' => $lot->id,
                'material_type' => $lot->material_type,
                'available_quantity' => app(InventoryService::class)->balances($lot)['available'],
                'unit' => $lot->unit,
            ]);
        });
    }

    /** An artisan product with $pieces in stock. */
    public function finishedProduct(int $pieces = 10): static
    {
        return $this->state(['listing_type' => ListingType::FinishedProduct])
            ->afterCreating(function (Listing $listing) use ($pieces) {
                $listing->productDetail()->create([
                    'product_category' => 'bags',
                    'product_condition' => 'new',
                    'production_quantity' => $pieces,
                    'unit' => 'piece',
                ]);
                app(InventoryService::class)->recordInitialStock($listing->fresh(), (string) $pieces);
            });
    }
}
