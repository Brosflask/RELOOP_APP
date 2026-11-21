<?php

namespace Tests\Concerns;

use App\Contracts\InventoryReservations;
use App\Models\Listing;
use App\Models\User;
use Tests\Fakes\FakeInventoryReservations;

/** Shared setup for Developer 4 feature tests. */
trait BuildsMarketplace
{
    protected FakeInventoryReservations $inventory;

    protected function setUpInventory(): void
    {
        $this->inventory = new FakeInventoryReservations;
        $this->app->instance(InventoryReservations::class, $this->inventory);
    }

    protected function listing(array $attributes = [], string $available = '10'): Listing
    {
        $listing = Listing::factory()->create($attributes);
        $this->inventory->setAvailable($listing, $available);

        return $listing;
    }

    protected function user(): User
    {
        return User::factory()->create();
    }
}
