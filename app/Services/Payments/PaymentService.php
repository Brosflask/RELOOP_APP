<?php

namespace App\Services\Payments;

use App\Contracts\InventoryReservations;
use App\Enums\OrderStatus;
use App\Enums\PaymentEventType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Events\ManualPaymentSubmitted;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\Momo\MomoException;
use App\Services\Payments\Momo\MomoStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Payment rules. A payment becomes successful ONLY when MTN's own status
 * endpoint says SUCCESSFUL with the right amount and currency. Callbacks,
 * 202 responses and the frontend can never mark anything paid.
 */
class PaymentService
{
    /** A pending payment younger than this is reused instead of starting another. */
    public const REUSE_PENDING_SECONDS = 300;

    /** A reference MTN still cannot find after this long is treated as failed. */
    public const NOT_FOUND_GRACE_MINUTES = 15;

    /** A payment still pending after this long is flagged for an admin. [GUESS] */
    public const ATTENTION_AFTER_HOURS = 24;

    public function __construct(
        private readonly MtnMomoService $momo,
        private readonly OrderService $orders,
        private readonly InventoryReservations $inventory,
    ) {
    }

    /**
     * Start (or reuse) a MoMo payment for an order. Returns the payment, which
     * is pending at best: the buyer still has to approve on their phone.
     */
    public function initiate(Order $order, User $buyer, string $msisdn, ?string $deliveryAddress = null): Payment
    {
        [$payment, $isNew] = DB::transaction(function () use ($order, $buyer, $msisdn, $deliveryAddress) {
            $order = $this->lockPayableOrder($order, $buyer, $deliveryAddress);

            $pending = $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();
            if ($pending) {
                if ($pending->created_at->gt(now()->subSeconds(self::REUSE_PENDING_SECONDS))) {
                    return [$pending, false];
                }
                // An older attempt is still open at MTN. Do not risk a double charge.
                throw new BusinessRuleException('A previous payment is still being confirmed by MTN. Please wait a few minutes and check again.', 'payment');
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'payer_id' => $order->buyer_id,
                'payee_id' => $order->seller_id,
                'provider' => PaymentProvider::MtnMomo,
                'transaction_reference' => (string) Str::uuid(),
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payer_msisdn' => $msisdn,
                'external_id' => $order->code,
            ]);

            return [$payment, true];
        });

        if ($isNew) {
            $this->submit($payment);
        }

        return $payment->refresh();
    }

    /**
     * Manual payment: the buyer has paid the seller outside the platform and
     * gives the transaction ID. The payment stays pending until the seller
     * or an admin confirms receipt. Nothing is marked paid automatically.
     */
    public function initiateManual(Order $order, User $buyer, string $msisdn, string $reference, ?string $deliveryAddress = null): Payment
    {
        return DB::transaction(function () use ($order, $buyer, $msisdn, $reference, $deliveryAddress) {
            $order = $this->lockPayableOrder($order, $buyer, $deliveryAddress);

            if ($pending = $order->payments()->where('status', PaymentStatus::Pending)->latest('id')->first()) {
                throw new BusinessRuleException('A payment for this order is already waiting for the seller to confirm.', 'payment');
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'payer_id' => $order->buyer_id,
                'payee_id' => $order->seller_id,
                'provider' => PaymentProvider::Manual,
                'transaction_reference' => (string) Str::uuid(),
                'amount' => $order->total_amount,
                'currency' => $order->currency,
                'payer_msisdn' => $msisdn,
                'external_id' => $order->code,
            ]);
            $payment->forceFill(['provider_reference' => Str::limit(trim($reference), 100, '')])->save();

            $this->log($payment, PaymentEventType::ManualSubmitted, null, ['reference' => $payment->provider_reference, 'by' => $buyer->id]);
            ManualPaymentSubmitted::dispatch($payment);

            return $payment;
        });
    }

    /** The seller (or an admin) confirms the money arrived. This is the human verification step. */
    public function confirmManual(Payment $payment, User $confirmer): Payment
    {
        return DB::transaction(function () use ($payment, $confirmer) {
            $payment = $this->lockPendingManual($payment);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($order->status !== OrderStatus::AwaitingPayment) {
                throw new BusinessRuleException('This order is no longer waiting for payment.', 'payment');
            }

            $payment->forceFill([
                'status' => PaymentStatus::Successful,
                'verified_at' => now(),
                'successful_order_id' => $order->id,
                'failure_reason' => null,
            ])->save();
            $this->log($payment, PaymentEventType::ManualConfirmed, null, ['by' => $confirmer->id]);

            $this->orders->transition($order, OrderStatus::Paid, $confirmer, 'Manual payment confirmed by '.$confirmer->name.' (ref '.$payment->provider_reference.')');
            $this->inventory->commitSale($order->load('items'));

            PaymentSucceeded::dispatch($payment);

            return $payment;
        });
    }

    /** The seller says the money did not arrive. The buyer can submit again. */
    public function rejectManual(Payment $payment, User $by, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $by, $reason) {
            $payment = $this->lockPendingManual($payment);
            $this->log($payment, PaymentEventType::ManualRejected, null, ['by' => $by->id, 'reason' => $reason]);
            $this->markFailed($payment, 'Seller did not receive this payment: '.$reason);

            return $payment;
        });
    }

    private function lockPendingManual(Payment $payment): Payment
    {
        $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

        if ($payment->provider !== PaymentProvider::Manual) {
            throw new BusinessRuleException('Only manual payments can be confirmed by hand.', 'payment');
        }
        if ($payment->status !== PaymentStatus::Pending) {
            throw new BusinessRuleException('This payment has already been settled.', 'payment');
        }

        return $payment;
    }

    /** Shared checks before any payment: buyer only, order unpaid, delivery address present. */
    private function lockPayableOrder(Order $order, User $buyer, ?string $deliveryAddress): Order
    {
        $order = Order::query()->lockForUpdate()->findOrFail($order->id);

        if ($order->buyer_id !== $buyer->id) {
            throw new BusinessRuleException('Only the buyer can pay for this order.', 'order');
        }
        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw new BusinessRuleException('This order is not waiting for payment.', 'order');
        }
        if ($deliveryAddress) {
            $order->update(['delivery_address' => $deliveryAddress]);
        }
        if (! $order->delivery_address) {
            throw new BusinessRuleException('Add a delivery address before paying.', 'delivery_address');
        }

        return $order;
    }

    /** Send the RequestToPay. Called once per payment, after the row is committed. */
    private function submit(Payment $payment): void
    {
        try {
            $result = $this->momo->requestToPay($payment);
        } catch (MomoException $e) {
            // We do not know whether MTN received it. Stay pending; reconciliation will ask MTN.
            $this->log($payment, PaymentEventType::Error, null, ['message' => $e->getMessage(), 'stage' => 'request_to_pay']);
            Log::warning('MoMo RequestToPay transport error', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return;
        }

        $this->log($payment, PaymentEventType::RequestToPay, $result->httpStatus, $result->body ?? []);

        if (! $result->accepted) {
            // MTN refused the request outright, so no money can have moved.
            DB::transaction(function () use ($payment, $result) {
                $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                if ($locked->status === PaymentStatus::Pending) {
                    $this->markFailed($locked, 'MTN rejected the request: '.$result->errorReason());
                }
            });
        }
    }

    /** Ask MTN for the current status and apply it. */
    public function verify(Payment $payment): Payment
    {
        if ($payment->status->isFinal() || $payment->provider !== PaymentProvider::MtnMomo) {
            return $payment;
        }

        try {
            $status = $this->momo->getTransactionStatus($payment->transaction_reference);
        } catch (MomoException $e) {
            $payment->forceFill([
                'last_checked_at' => now(),
                'check_attempts' => $payment->check_attempts + 1,
            ])->save();
            $this->log($payment, PaymentEventType::Error, null, ['message' => $e->getMessage(), 'stage' => 'status_check']);

            return $payment;
        }

        return $this->applyProviderStatus($payment, $status);
    }

    /**
     * Apply a status obtained from MTN's status endpoint. Idempotent: a final
     * payment is never changed, so duplicate callbacks and overlapping jobs
     * are harmless.
     */
    public function applyProviderStatus(Payment $payment, MomoStatus $status): Payment
    {
        return DB::transaction(function () use ($payment, $status) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            $this->log($payment, PaymentEventType::StatusCheck, $status->httpStatus, $status->raw);

            if ($payment->status->isFinal()) {
                return $payment;
            }

            $payment->forceFill([
                'last_checked_at' => now(),
                'check_attempts' => $payment->check_attempts + 1,
            ]);

            match ($status->state) {
                MomoStatus::SUCCESSFUL => $this->handleSuccessful($payment, $status),
                MomoStatus::FAILED => $this->markFailed($payment, $status->reason ?? 'Payment failed at MTN'),
                MomoStatus::NOT_FOUND => $this->handleNotFound($payment),
                default => $this->handleStillPending($payment),
            };

            return $payment;
        });
    }

    private function handleSuccessful(Payment $payment, MomoStatus $status): void
    {
        $currencyOk = $status->currency === null || strtoupper($status->currency) === strtoupper($this->momo->providerCurrency());

        if (! $status->amountMatches((string) $payment->amount) || ! $currencyOk) {
            // Never accept a payment for the wrong amount. A person must look at it.
            $payment->forceFill([
                'requires_attention' => true,
                'attention_reason' => sprintf('MTN reports %s %s, expected %s %s', $status->amount, $status->currency, $payment->amount, $this->momo->providerCurrency()),
            ])->save();
            Log::alert('MoMo amount mismatch', ['payment_id' => $payment->id]);

            return;
        }

        $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
        $alreadyPaid = Payment::query()
            ->where('successful_order_id', $order->id)
            ->whereKeyNot($payment->id)
            ->exists();

        $payment->forceFill([
            'status' => PaymentStatus::Successful,
            'provider_reference' => $status->financialTransactionId,
            'verified_at' => now(),
            'failure_reason' => null,
        ]);

        if ($alreadyPaid || $order->status !== OrderStatus::AwaitingPayment) {
            // Money really moved, so record it as successful, but it must be refunded or matched by an admin.
            $payment->forceFill([
                'requires_attention' => true,
                'attention_reason' => $alreadyPaid
                    ? 'Second successful payment for an order that was already paid. Refund needed.'
                    : 'Payment succeeded but the order is '.$order->status->label().'. Review needed.',
            ])->save();
            $this->log($payment, PaymentEventType::StatusChanged, null, ['to' => 'successful', 'attention' => true]);
            Log::alert('MoMo payment needs attention', ['payment_id' => $payment->id]);

            return;
        }

        $payment->forceFill(['successful_order_id' => $order->id])->save();
        $this->log($payment, PaymentEventType::StatusChanged, null, ['to' => 'successful']);

        $this->orders->transition($order, OrderStatus::Paid, null, 'MTN MoMo payment verified ('.$status->financialTransactionId.')');
        $this->inventory->commitSale($order->load('items'));

        PaymentSucceeded::dispatch($payment);
    }

    private function markFailed(Payment $payment, string $reason): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_reason' => Str::limit($reason, 250),
            'verified_at' => now(),
        ])->save();
        $this->log($payment, PaymentEventType::StatusChanged, null, ['to' => 'failed', 'reason' => $reason]);

        PaymentFailed::dispatch($payment);
    }

    private function handleNotFound(Payment $payment): void
    {
        if ($payment->created_at->lte(now()->subMinutes(self::NOT_FOUND_GRACE_MINUTES))) {
            $this->markFailed($payment, 'MTN has no record of this payment request.');

            return;
        }

        $payment->save();
    }

    private function handleStillPending(Payment $payment): void
    {
        if ($payment->created_at->lte(now()->subHours(self::ATTENTION_AFTER_HOURS))) {
            $payment->forceFill([
                'requires_attention' => true,
                'attention_reason' => 'Still pending at MTN after '.self::ATTENTION_AFTER_HOURS.' hours.',
            ]);
        }

        $payment->save();
    }

    /** Record traffic with MTN. Secrets are stripped; only response bodies are stored. */
    public function log(Payment $payment, PaymentEventType $type, ?int $httpStatus, array $payload): void
    {
        $payment->events()->create([
            'type' => $type,
            'http_status' => $httpStatus,
            'payload' => Arr::except($payload, ['access_token', 'apiKey', 'api_key', 'Authorization', 'token']),
        ]);
    }
}
