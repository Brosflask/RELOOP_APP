<?php

namespace App\Enums;

/**
 * Plastic types by resin code. SHARED with Developer 2 (collection records and
 * batches use the same values). Agree on this list before both modules merge;
 * whoever merges first owns the file and the other reuses it.
 */
enum MaterialType: string
{
    case Pet = 'pet';     // 1, bottles
    case Hdpe = 'hdpe';   // 2, jerrycans, containers
    case Pvc = 'pvc';     // 3, pipes
    case Ldpe = 'ldpe';   // 4, sachets, bags
    case Pp = 'pp';       // 5, chairs, caps
    case Ps = 'ps';       // 6, food packs
    case Mixed = 'mixed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pet => 'PET (1)',
            self::Hdpe => 'HDPE (2)',
            self::Pvc => 'PVC (3)',
            self::Ldpe => 'LDPE (4)',
            self::Pp => 'PP (5)',
            self::Ps => 'PS (6)',
            self::Mixed => 'Mixed plastics',
            self::Other => 'Other',
        };
    }

    /** @return list<array{value: string, label: string}> for React selects */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
