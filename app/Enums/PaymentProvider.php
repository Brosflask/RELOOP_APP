<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case MtnMomo = 'mtn_momo';

    /**
     * Buyer pays the seller outside the platform (MoMo transfer, cash) and the
     * seller or an admin confirms receipt. Used until MTN MoMo is connected.
     */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::MtnMomo => 'MTN MoMo',
            self::Manual => 'Manual (confirmed by seller)',
        };
    }
}
