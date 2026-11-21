<?php

namespace App\Jobs;

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hourly. Completes orders delivered 7 days ago [GUESS] that have no open complaint. */
class CompleteDeliveredOrders implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const AFTER_DAYS = 7;

    public function handle(OrderService $orders): void
    {
        Order::query()
            ->where('status', OrderStatus::Delivered)
            ->where('delivered_at', '<=', now()->subDays(self::AFTER_DAYS))
            ->whereDoesntHave('complaints', fn ($q) => $q->whereIn('status', [ComplaintStatus::Open, ComplaintStatus::UnderReview]))
            ->chunkById(100, function ($batch) use ($orders) {
                foreach ($batch as $order) {
                    try {
                        $orders->transition($order, OrderStatus::Completed, null, 'Completed automatically '.self::AFTER_DAYS.' days after delivery');
                    } catch (BusinessRuleException) {
                        // Changed meanwhile.
                    }
                }
            });
    }
}
