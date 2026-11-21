<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Status rules (e.g. ship only when paid) are enforced by OrderService's state machine. */
class OrderFulfilmentController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function ship(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('ship', $order);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        $this->orders->ship($order, $request->user(), $note);

        return back()->with('success', 'Order marked as shipped.');
    }

    public function deliver(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('markDelivered', $order);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        $this->orders->markDelivered($order, $request->user(), $note);

        return back()->with('success', 'Order marked as delivered.');
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('confirmDelivery', $order);

        $this->orders->confirmDelivery($order, $request->user());

        return back()->with('success', 'Thanks for confirming. The order is complete.');
    }
}
