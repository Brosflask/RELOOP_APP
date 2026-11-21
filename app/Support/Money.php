<?php

namespace App\Support;

/**
 * Exact decimal arithmetic for money (2 dp) and quantities (3 dp) using
 * integer minor units, so totals never suffer float rounding.
 */
final class Money
{
    public static function toCents(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function toMilli(string|int|float $quantity): int
    {
        return (int) round(((float) $quantity) * 1000);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function quantity(string|int|float $quantity): string
    {
        return number_format(self::toMilli($quantity) / 1000, 3, '.', '');
    }

    /** unit_price × quantity, rounded half up to the cent. */
    public static function lineTotal(string $unitPrice, string $quantity): string
    {
        $cents = intdiv(self::toCents($unitPrice) * self::toMilli($quantity) + 500, 1000);

        return self::fromCents($cents);
    }

    public static function compareQuantity(string $a, string $b): int
    {
        return self::toMilli($a) <=> self::toMilli($b);
    }
}
