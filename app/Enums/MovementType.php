<?php

namespace App\Enums;

/**
 * Kinds of rows in the inventory_movements ledger.
 *
 * Each row changes two balances of one stock bucket (a material lot, or a
 * finished-product listing):
 *   quantity_change  = change to stock on hand
 *   reserved_change  = change to stock held for unpaid orders
 * Available = on hand - reserved.
 */
enum MovementType: string
{
    case InitialStock = 'initial_stock';          // opening stock (product listings, imports)
    case ProcessingOutput = 'processing_output';  // lot created by a processing record
    case Reservation = 'reservation';             // order placed, waiting for payment
    case Sale = 'sale';                           // payment verified, stock leaves
    case Transfer = 'transfer';                   // stock arriving in a buyer's lot after a sale
    case Adjustment = 'adjustment';               // manual correction (damage, recount)
    case Reversal = 'reversal';                   // reservation released (order cancelled)

    public function label(): string
    {
        return match ($this) {
            self::InitialStock => 'Opening stock',
            self::ProcessingOutput => 'Processing output',
            self::Reservation => 'Reserved for order',
            self::Sale => 'Sold',
            self::Transfer => 'Transferred in',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reservation released',
        };
    }
}
