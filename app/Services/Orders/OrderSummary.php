<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * Read-only order summaries for dashboards. Developer 3's recycler and
 * artisan dashboards can pass forSeller() to the SellerOrdersWidget component.
 */
class OrderSummary
{
    /** @return array<string, int> status => count */
    public static function countsFor(User $user, string $side): array
    {
        $column = $side === 'seller' ? 'seller_id' : 'buyer_id';

        $counts = Order::query()
            ->where($column, $user->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(OrderStatus::cases())
            ->mapWithKeys(fn ($s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
            ->all();
    }

    public static function list(User $user, string $side, array $statuses, int $limit = 5): array
    {
        $column = $side === 'seller' ? 'seller_id' : 'buyer_id';

        return Order::query()
            ->where($column, $user->id)
            ->whereIn('status', $statuses)
            ->with(['items:id,order_id,title', 'buyer:id,name', 'seller:id,name'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'title' => $o->items->first()?->title,
                'total_amount' => (string) $o->total_amount,
                'currency' => $o->currency,
                'other_party' => $side === 'seller' ? $o->buyer->only('id', 'name') : $o->seller->only('id', 'name'),
                'created_at' => $o->created_at->toIso8601String(),
            ])
            ->all();
    }

    /** Orders a seller has to act on: paid (ship) and shipped (deliver). */
    public static function forSeller(User $seller): array
    {
        return [
            'counts' => self::countsFor($seller, 'seller'),
            'to_fulfil' => self::list($seller, 'seller', [OrderStatus::Paid->value, OrderStatus::Shipped->value]),
        ];
    }
}
