<?php

namespace Tests\Concerns;

use App\Contracts\InventoryReservations;
use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderService;
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

    /** An order bought at the listed price, optionally moved through the given statuses. */
    protected function orderIn(OrderStatus ...$path): Order
    {
        $listing = $this->listing(['price' => '20.00']);
        $buyer = $this->user();
        $service = app(OrderService::class);
        $order = $service->buyAtListedPrice($listing, $buyer, '1', 'Tamale');

        foreach ($path as $status) {
            $service->transition($order, $status);
        }

        return $order->fresh();
    }
}
