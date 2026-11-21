<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Disputes/Index', [
            'orders' => Order::query()
                ->where('status', OrderStatus::Disputed)
                ->with(['buyer:id,name', 'seller:id,name', 'items:id,order_id,title', 'complaints:id,order_id,subject,status'])
                ->oldest('updated_at')
                ->paginate(25)
                ->through(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code,
                    'title' => $o->items->first()?->title,
                    'buyer' => $o->buyer->name,
                    'seller' => $o->seller->name,
                    'total_amount' => (string) $o->total_amount,
                    'currency' => $o->currency,
                    'complaints' => $o->complaints->map->only('id', 'subject', 'status'),
                ]),
        ]);
    }

    /**
     * Settle a dispute. "complete" releases the order to the seller;
     * "cancel" ends it, and the verified payment is flagged because the
     * buyer must be refunded (MTN refunds/disbursements need approval, so
     * the refund itself happens outside the platform for now).
     */
    public function resolve(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['complete', 'cancel'])],
            'note' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $order, $orders, $request) {
            if ($data['outcome'] === 'complete') {
                $orders->transition($order, OrderStatus::Completed, $request->user(), 'Dispute resolved: '.$data['note']);

                return;
            }

            $orders->transition($order, OrderStatus::Cancelled, $request->user(), 'Dispute resolved: '.$data['note'], [
                'cancellation_reason' => 'Dispute: '.$data['note'],
            ]);

            $order->payments()->where('status', PaymentStatus::Successful)->get()->each(fn ($p) => $p->forceFill([
                'requires_attention' => true,
                'attention_reason' => 'Refund required: order cancelled after dispute.',
            ])->save());
        });

        return back()->with('success', 'Dispute resolved.');
    }
}
