<?php

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $allowed = [
            ['awaiting_payment', 'paid'], ['awaiting_payment', 'cancelled'],
            ['paid', 'shipped'], ['paid', 'disputed'],
            ['shipped', 'delivered'], ['shipped', 'disputed'],
            ['delivered', 'completed'], ['delivered', 'disputed'],
            ['disputed', 'completed'], ['disputed', 'cancelled'],
        ];

        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $expected = in_array([$from->value, $to->value], $allowed, true);
                $this->assertSame($expected, $from->canTransitionTo($to), "{$from->value} -> {$to->value}");
            }
        }
    }

    public function test_paid_order_cannot_be_cancelled_directly(): void
    {
        $this->assertFalse(OrderStatus::Paid->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_final_states(): void
    {
        $this->assertTrue(OrderStatus::Completed->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
        $this->assertFalse(OrderStatus::Disputed->isFinal());
    }
}
