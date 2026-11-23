<?php

namespace App\Enums;

/**
 * Developer 2. Status of what a collector actually picked up.
 *
 * The collector records the weight (pending_confirmation). The household
 * confirms it or disputes it. A disputed record is corrected and confirmed.
 * Walk-in records with no household start as confirmed.
 */
enum CollectionRecordStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'Waiting for household',
            self::Confirmed => 'Confirmed',
            self::Disputed => 'Disputed',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PendingConfirmation => [self::Confirmed, self::Disputed],
            self::Disputed => [self::Confirmed],
            self::Confirmed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
