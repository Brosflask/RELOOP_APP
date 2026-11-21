<?php

namespace App\Http\Controllers\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InitiatePaymentRequest;
use App\Jobs\VerifyMomoPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function create(Request $request, Order $order): Response|RedirectResponse
    {
        Gate::authorize('pay', $order);

        if ($order->status !== OrderStatus::AwaitingPayment) {
            return to_route('orders.show', $order);
        }

        $order->load('items:id,order_id,title,quantity,unit,line_total');
        $pending = $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();

        return Inertia::render('Orders/Pay', [
            'order' => [
                'id' => $order->id,
                'code' => $order->code,
                'total_amount' => (string) $order->total_amount,
                'currency' => $order->currency,
                'delivery_address' => $order->delivery_address,
                'items' => $order->items->map->only('id', 'title', 'quantity', 'unit', 'line_total'),
            ],
            'pending_payment_id' => $pending?->id,
            'sandbox' => config('mtn_momo.environment') === 'sandbox',
        ]);
    }

    public function store(InitiatePaymentRequest $request, Order $order): RedirectResponse
    {
        $payment = $this->payments->initiate(
            $order,
            $request->user(),
            $request->string('msisdn'),
            $request->input('delivery_address'),
        );

        return to_route('payments.show', $payment);
    }

    public function show(Request $request, Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        return Inertia::render('Payments/Show', [
            'payment' => $this->present($payment->load('order:id,code,status')),
        ]);
    }

    /**
     * Polled by the waiting screen. Reading the status may queue a check with
     * MTN (at most every 15 seconds); it never changes anything by itself.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        Gate::authorize('view', $payment);

        if ($payment->status === PaymentStatus::Pending
            && ! $payment->requires_attention
            && $payment->created_at->lte(now()->subSeconds(5))
            && ($payment->last_checked_at === null || $payment->last_checked_at->lte(now()->subSeconds(15)))) {
            VerifyMomoPayment::dispatch($payment->id);
            $payment->refresh();
        }

        return response()->json($this->present($payment->load('order:id,code,status')));
    }

    private function present(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'status' => $payment->status->value,
            'amount' => (string) $payment->amount,
            'currency' => $payment->currency,
            'failure_reason' => $payment->failure_reason,
            'payer_msisdn' => $payment->payer_msisdn,
            'order' => [
                'id' => $payment->order->id,
                'code' => $payment->order->code,
                'status' => $payment->order->status->value,
            ],
        ];
    }
}
