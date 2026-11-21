<?php

namespace App\Http\Controllers\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    /** Orders the user is buying (default) or selling (?tab=selling). */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['buying', 'selling'])],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ]);
        $tab = $validated['tab'] ?? 'buying';
        $user = $request->user();

        $orders = Order::query()
            ->where($tab === 'selling' ? 'seller_id' : 'buyer_id', $user->id)
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with(['items:id,order_id,title,quantity,unit', 'buyer:id,name', 'seller:id,name'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'code' => $o->code,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'total_amount' => (string) $o->total_amount,
                'currency' => $o->currency,
                'title' => $o->items->first()?->title,
                'other_party' => $tab === 'selling' ? $o->buyer->only('id', 'name') : $o->seller->only('id', 'name'),
                'created_at' => $o->created_at->toIso8601String(),
            ]);

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'tab' => $tab,
            'filters' => ['status' => $validated['status'] ?? null],
            'statuses' => collect(OrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $listing = Listing::findOrFail($request->integer('listing_id'));

        $order = $this->orders->buyAtListedPrice(
            $listing,
            $request->user(),
            (string) $request->input('quantity'),
            $request->input('delivery_address'),
            $request->input('delivery_notes'),
        );

        return to_route('orders.show', $order)->with('success', 'Order '.$order->code.' created.');
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);

        $user = $request->user();
        $order->load(['items', 'agreement', 'buyer:id,name', 'seller:id,name', 'statusHistories.changedBy:id,name', 'payments']);

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'subtotal' => (string) $order->subtotal,
                'total_amount' => (string) $order->total_amount,
                'currency' => $order->currency,
                'delivery_address' => $order->delivery_address,
                'delivery_notes' => $order->delivery_notes,
                'conversation_id' => $order->conversation_id,
                'buyer' => $order->buyer->only('id', 'name'),
                'seller' => $order->seller->only('id', 'name'),
                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'listing_id' => $i->listing_id,
                    'title' => $i->title,
                    'listing_type' => $i->listing_type,
                    'quantity' => (string) $i->quantity,
                    'unit' => $i->unit,
                    'unit_price' => (string) $i->unit_price,
                    'line_total' => (string) $i->line_total,
                ]),
                'history' => $order->statusHistories->map(fn ($h) => [
                    'to_status' => $h->to_status->value,
                    'label' => $h->to_status->label(),
                    'by' => $h->changedBy?->name,
                    'note' => $h->note,
                    'at' => $h->created_at->toIso8601String(),
                ]),
                'payments' => $order->payments->sortByDesc('id')->values()->map(fn ($p) => [
                    'id' => $p->id,
                    'status' => $p->status->value,
                    'amount' => (string) $p->amount,
                    'created_at' => $p->created_at->toIso8601String(),
                ]),
            ],
            'viewer_role' => $user->id === $order->buyer_id ? 'buyer' : ($user->id === $order->seller_id ? 'seller' : 'admin'),
            'can' => [
                'pay' => $user->id === $order->buyer_id && $order->status === OrderStatus::AwaitingPayment,
                'cancel' => $order->isParticipant($user) && $order->status === OrderStatus::AwaitingPayment,
            ],
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->orders->cancel($order, $request->user(), $request->input('reason'));

        return back()->with('success', 'Order cancelled.');
    }
}
