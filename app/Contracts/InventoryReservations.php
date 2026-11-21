<?php

namespace App\Contracts;

use App\Models\Listing;
use App\Models\Order;

/**
 * What Developer 4's order flow needs from Developer 3's inventory module.
 *
 * Developer 3's InventoryService implements this and binds it in a service
 * provider. Developer 4 never writes inventory_movements directly. Every
 * method is called inside an open DB transaction and must record its own
 * inventory_movements rows (reservation, reversal, sale).
 *
 * PROPOSED CONTRACT: signatures need Developer 3's sign-off.
 */
interface InventoryReservations
{
    /** Quantity that can still be ordered right now, as a decimal string (3 dp). */
    public function availableQuantity(Listing $listing): string;

    /** Reserve the quantities of every item in the order. Throws if not enough stock. */
    public function reserve(Order $order): void;

    /** Release an order's reservation (order cancelled before payment). */
    public function release(Order $order): void;

    /** Turn an order's reservation into a sale (payment verified). */
    public function commitSale(Order $order): void;
}
