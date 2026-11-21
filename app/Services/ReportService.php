<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin analytics. Read-only. Sales figures count only payments MTN
 * verified as successful and linked to their order (successful_order_id).
 */
class ReportService
{
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $key = 'reports.summary.'.$from->toDateString().'.'.$to->toDateString();

        return Cache::remember($key, 300, function () use ($from, $to) {
            $verified = Payment::query()
                ->whereNotNull('successful_order_id')
                ->whereBetween('verified_at', [$from->startOfDay(), $to->endOfDay()]);

            $finished = Payment::query()
                ->whereIn('status', [PaymentStatus::Successful, PaymentStatus::Failed])
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
            $finishedCount = (clone $finished)->count();
            $failedCount = (clone $finished)->where('status', PaymentStatus::Failed)->count();

            return [
                'gmv' => number_format((float) (clone $verified)->sum('amount'), 2, '.', ''),
                'paid_orders' => (clone $verified)->count(),
                'orders_by_status' => $this->countBy(Order::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]), OrderStatus::cases()),
                'payments_by_status' => $this->countBy(Payment::query()->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]), PaymentStatus::cases()),
                'failed_payment_rate' => $finishedCount ? round($failedCount / $finishedCount * 100, 1) : null,
                'payments_needing_attention' => Payment::where('requires_attention', true)->count(),
                'open_complaints' => Complaint::whereIn('status', [ComplaintStatus::Open, ComplaintStatus::UnderReview])->count(),
                'open_disputes' => Order::where('status', OrderStatus::Disputed)->count(),
            ];
        });
    }

    /** Verified sales per day, split by listing type. */
    public function salesByDay(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('payments')
            ->join('order_items', 'order_items.order_id', '=', 'payments.successful_order_id')
            ->whereNotNull('payments.successful_order_id')
            ->whereBetween('payments.verified_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('DATE(payments.verified_at) as day, order_items.listing_type, SUM(order_items.line_total) as total, COUNT(DISTINCT payments.id) as orders')
            ->groupBy('day', 'order_items.listing_type')
            ->orderBy('day')
            ->get();

        return $rows->groupBy('day')->map(fn ($day, $date) => [
            'day' => $date,
            'raw_material' => number_format((float) $day->where('listing_type', 'raw_material')->sum('total'), 2, '.', ''),
            'finished_product' => number_format((float) $day->where('listing_type', 'finished_product')->sum('total'), 2, '.', ''),
            'orders' => (int) $day->sum('orders'),
        ])->values()->all();
    }

    public function topSellers(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        return DB::table('payments')
            ->join('users', 'users.id', '=', 'payments.payee_id')
            ->whereNotNull('payments.successful_order_id')
            ->whereBetween('payments.verified_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('users.id, users.name, SUM(payments.amount) as total, COUNT(*) as orders')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'total' => number_format((float) $r->total, 2, '.', ''),
                'orders' => (int) $r->orders,
                'average_rating' => ($avg = DB::table('reviews')->where('reviewee_id', $r->id)->where('is_hidden', false)->avg('rating')) ? round((float) $avg, 2) : null,
            ])->all();
    }

    /** Kilograms of raw material sold, by listing (from order snapshots). */
    public function materialsSold(CarbonImmutable $from, CarbonImmutable $to, int $limit = 10): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.listing_type', 'raw_material')
            ->where('order_items.unit', 'kg')
            ->whereNotNull('orders.paid_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw('order_items.title, SUM(order_items.quantity) as kg, SUM(order_items.line_total) as total')
            ->groupBy('order_items.title')
            ->orderByDesc('kg')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'title' => $r->title,
                'kg' => number_format((float) $r->kg, 3, '.', ''),
                'total' => number_format((float) $r->total, 2, '.', ''),
            ])->all();
    }

    /**
     * Platform impact. Kilograms collected come from Developer 2's
     * collection_records table, read-only. [GUESS] on the weight column name;
     * returns null until that table and column exist.
     */
    public function impact(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $collected = null;
        foreach (['actual_weight_kg', 'weight_kg', 'quantity_kg'] as $column) {
            if (Schema::hasTable('collection_records') && Schema::hasColumn('collection_records', $column)) {
                $collected = number_format((float) DB::table('collection_records')
                    ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                    ->sum($column), 3, '.', '');
                break;
            }
        }

        $sold = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.unit', 'kg')
            ->whereNotNull('orders.paid_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('order_items.quantity');

        return [
            'kg_collected' => $collected,
            'kg_sold' => number_format((float) $sold, 3, '.', ''),
        ];
    }

    /** @param list<\BackedEnum> $cases */
    private function countBy($query, array $cases): array
    {
        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect($cases)->mapWithKeys(fn ($c) => [$c->value => (int) ($counts[$c->value] ?? 0)])->all();
    }
}
