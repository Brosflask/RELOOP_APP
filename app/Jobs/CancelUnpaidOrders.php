<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly. Cancels orders left unpaid for 48 hours [GUESS] and releases their
 * stock. Orders with a payment still pending at MTN are left alone.
 */
class CancelUnpaidOrders implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const AFTER_HOURS = 48;

    public function handle(OrderService $orders): void
    {
        Order::query()
            ->where('status', OrderStatus::AwaitingPayment)
            ->where('created_at', '<=', now()->subHours(self::AFTER_HOURS))
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Pending))
            ->chunkById(100, function ($batch) use ($orders) {
                foreach ($batch as $order) {
                    try {
                        $orders->transition($order, OrderStatus::Cancelled, null, 'Not paid within '.self::AFTER_HOURS.' hours', [
                            'cancellation_reason' => 'Payment window expired',
                        ]);
                    } catch (BusinessRuleException) {
                        // Status changed meanwhile (e.g. just paid). Leave it.
                    }
                }
            });
    }
}
