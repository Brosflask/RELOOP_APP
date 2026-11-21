<?php

namespace Tests\Fakes;

use App\Contracts\InventoryReservations;
use App\Models\Listing;
use App\Models\Order;
use App\Support\Money;
use RuntimeException;

/**
 * Test-only stand-in for Developer 3's InventoryService. Never bound in production.
 */
class FakeInventoryReservations implements InventoryReservations
{
    /** @var array<int, int> listing id => available quantity in thousandths */
    public array $available = [];

    /** @var list<array{0: string, 1: int}> */
    public array $calls = [];

    public function setAvailable(Listing $listing, string $quantity): void
    {
        $this->available[$listing->id] = Money::toMilli($quantity);
    }

    public function availableQuantity(Listing $listing): string
    {
        return Money::quantity(($this->available[$listing->id] ?? 0) / 1000);
    }

    public function reserve(Order $order): void
    {
        foreach ($order->items as $item) {
            $left = ($this->available[$item->listing_id] ?? 0) - Money::toMilli((string) $item->quantity);
            if ($left < 0) {
                throw new RuntimeException('Not enough stock');
            }
            $this->available[$item->listing_id] = $left;
        }
        $this->calls[] = ['reserve', $order->id];
    }

    public function release(Order $order): void
    {
        foreach ($order->items as $item) {
            $this->available[$item->listing_id] = ($this->available[$item->listing_id] ?? 0) + Money::toMilli((string) $item->quantity);
        }
        $this->calls[] = ['release', $order->id];
    }

    public function commitSale(Order $order): void
    {
        $this->calls[] = ['commitSale', $order->id];
    }

    public function called(string $method, Order $order): bool
    {
        return in_array([$method, $order->id], $this->calls, true);
    }
}
