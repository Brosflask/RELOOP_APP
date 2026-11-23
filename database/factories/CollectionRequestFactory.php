<?php

namespace Database\Factories;

use App\Enums\CollectionStatus;
use App\Models\CollectionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Developer 2.
 *
 * @extends Factory<CollectionRequest>
 */
class CollectionRequestFactory extends Factory
{
    protected $model = CollectionRequest::class;

    public function definition(): array
    {
        return [
            'household_id' => User::factory(),
            'status' => CollectionStatus::Pending,
            'pickup_address' => fake()->streetAddress(),
            'area' => fake()->randomElement(['Tamale Central', 'Sagnarigu', 'Kumbungu', 'Savelugu']),
            'gps_address' => 'NT-'.fake()->numerify('###-####'),
            'contact_phone' => '024'.fake()->numerify('#######'),
            'preferred_date' => now()->addDays(2)->toDateString(),
            'preferred_time_slot' => 'morning',
            'estimated_weight_kg' => '12.500',
        ];
    }

    public function assignedTo(?User $collector = null): static
    {
        return $this->state(fn () => [
            'collector_id' => $collector?->id ?? User::factory(),
            'status' => CollectionStatus::Assigned,
            'assigned_at' => now(),
        ]);
    }

    public function inProgress(?User $collector = null): static
    {
        return $this->assignedTo($collector)->state(fn () => [
            'status' => CollectionStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(?User $collector = null): static
    {
        return $this->inProgress($collector)->state(fn () => [
            'status' => CollectionStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CollectionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $attributes['household_id'],
            'cancellation_reason' => 'No longer needed',
        ]);
    }
}
