<?php

namespace App\Enums;

enum MaterialLotStatus: string
{
    case Available = 'available';
    case Depleted = 'depleted';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Depleted => 'Used up',
        };
    }
}
