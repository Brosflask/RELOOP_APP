<?php

namespace App\Enums;

/**
 * Developer 2. Status of a material batch (collected waste, not processed output).
 *
 * open -> dispatched -> received          (normal path)
 * dispatched -> rejected -> open          (recycler refuses, collector takes it back)
 * open -> consolidated                    (merged into a bigger batch)
 */
enum BatchStatus: string
{
    case Open = 'open';
    case Dispatched = 'dispatched';
    case Received = 'received';
    case Rejected = 'rejected';
    case Consolidated = 'consolidated';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Dispatched => 'On the way to recycler',
            self::Received => 'Received by recycler',
            self::Rejected => 'Rejected by recycler',
            self::Consolidated => 'Merged into another batch',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::Dispatched, self::Consolidated],
            self::Dispatched => [self::Received, self::Rejected],
            self::Rejected => [self::Open],
            self::Received, self::Consolidated => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Collection records can still be added or removed. */
    public function isEditable(): bool
    {
        return $this === self::Open;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
