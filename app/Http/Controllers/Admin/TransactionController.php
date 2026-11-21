<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'attention' => ['nullable', 'boolean'],
            'stuck' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $payments = Payment::query()
            ->with(['order:id,code', 'payer:id,name', 'payee:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('attention'), fn ($q) => $q->where('requires_attention', true))
            ->when($request->boolean('stuck'), fn ($q) => $q->where('status', PaymentStatus::Pending)->where('created_at', '<=', now()->subHour()))
            ->when($filters['search'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('transaction_reference', $term)
                    ->orWhere('provider_reference', $term)
                    ->orWhere('payer_msisdn', $term)
                    ->orWhereHas('order', fn ($o) => $o->where('code', $term)));
            })
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $p) => [
                'id' => $p->id,
                'created_at' => $p->created_at->toIso8601String(),
                'order' => $p->order?->only('id', 'code'),
                'payer' => $p->payer?->name,
                'payee' => $p->payee?->name,
                'amount' => (string) $p->amount,
                'currency' => $p->currency,
                'status' => $p->status->value,
                'requires_attention' => $p->requires_attention,
            ]);

        return Inertia::render('Admin/Transactions/Index', [
            'payments' => $payments,
            'filters' => $filters + ['attention' => $request->boolean('attention'), 'stuck' => $request->boolean('stuck')],
            'statuses' => array_map(fn ($s) => $s->value, PaymentStatus::cases()),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $payment->load(['order:id,code,status,total_amount', 'payer:id,name', 'payee:id,name', 'events']);

        return Inertia::render('Admin/Transactions/Show', [
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status->value,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'provider' => $payment->provider->value,
                'transaction_reference' => $payment->transaction_reference,
                'provider_reference' => $payment->provider_reference,
                'payer' => $payment->payer?->only('id', 'name'),
                'payee' => $payment->payee?->only('id', 'name'),
                'payer_msisdn' => $payment->payer_msisdn,
                'failure_reason' => $payment->failure_reason,
                'requires_attention' => $payment->requires_attention,
                'attention_reason' => $payment->attention_reason,
                'check_attempts' => $payment->check_attempts,
                'last_checked_at' => $payment->last_checked_at?->toIso8601String(),
                'verified_at' => $payment->verified_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
                'order' => $payment->order ? [
                    'id' => $payment->order->id,
                    'code' => $payment->order->code,
                    'status' => $payment->order->status->value,
                    'total_amount' => (string) $payment->order->total_amount,
                ] : null,
                'events' => $payment->events->map(fn ($e) => [
                    'id' => $e->id,
                    'type' => $e->type->value,
                    'http_status' => $e->http_status,
                    'payload' => $e->payload,
                    'created_at' => $e->created_at->toIso8601String(),
                ]),
            ],
            'can_recheck' => $payment->status === PaymentStatus::Pending,
        ]);
    }

    /** Ask MTN now. Uses the same verification as callbacks and the scheduled job. */
    public function recheck(Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('recheck', $payment);

        $payments->verify($payment);

        return back()->with('success', 'Checked with MTN. Status: '.$payment->fresh()->status->value.'.');
    }

    /** Clear the attention flag once an admin has dealt with it (e.g. refunded outside the platform). */
    public function acknowledge(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);

        $payment->forceFill([
            'requires_attention' => false,
            'attention_reason' => trim(($payment->attention_reason ? $payment->attention_reason.' | ' : '').'Resolved by '.$request->user()->name.': '.$data['note']),
        ])->save();

        return back()->with('success', 'Marked as handled.');
    }
}
