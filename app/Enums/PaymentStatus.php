<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    /** A final payment is never changed by a callback or status check. */
    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
