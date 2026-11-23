<?php

namespace App\Enums;

/**
 * Developer 2. Status of a household's collection (pickup) request.
 *
 * pending -> assigned -> in_progress -> completed
 * A request can be cancelled until it is completed, and an assigned request
 * can go back to pending if the collector drops it.
 */
enum CollectionStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for a collector',
            self::Assigned => 'Collector assigned',
            self::InProgress => 'Pickup in progress',
            self::Completed => 'Collected',
            self::Cancelled => 'Cancelled',
        };
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Assigned, self::Cancelled],
            self::Assigned => [self::InProgress, self::Pending, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Still waiting to be collected (shown on the collector's job list). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::InProgress], true);
    }

    /** @return list<array{value: string, label: string}> for React selects */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
