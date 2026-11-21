<?php

namespace App\Enums;

/**
 * Order lifecycle. Negotiation happens in conversations/offers, so an order
 * only exists once terms are agreed (see docs/developer-4-plan.md, 3.5).
 */
enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::AwaitingPayment => [self::Paid, self::Cancelled],
            self::Paid => [self::Shipped, self::Disputed],
            self::Shipped => [self::Delivered, self::Disputed],
            self::Delivered => [self::Completed, self::Disputed],
            self::Disputed => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            default => ucfirst($this->value),
        };
    }
}
