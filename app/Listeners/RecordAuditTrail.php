<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Log;

/**
 * Bridges Developer 4's events into the shared audit log. Order history and
 * payment_events already keep a full trail in Dev 4's tables; this adds a
 * platform-wide entry once the shared AuditService exists (owner to agree,
 * proposed Developer 1). Until then it writes to the application log.
 */
class RecordAuditTrail
{
    public function handleOrderStatusChanged(OrderStatusChanged $e): void
    {
        $this->record($e->by?->id, 'order.'.$e->to->value, $e->order, ['from' => $e->from?->value, 'to' => $e->to->value]);
    }

    public function handlePaymentSucceeded(PaymentSucceeded $e): void
    {
        $this->record(null, 'payment.successful', $e->payment, [
            'amount' => (string) $e->payment->amount, 'provider_reference' => $e->payment->provider_reference,
        ]);
    }

    public function handlePaymentFailed(PaymentFailed $e): void
    {
        $this->record(null, 'payment.failed', $e->payment, ['reason' => $e->payment->failure_reason]);
    }

    private function record(?int $userId, string $action, object $subject, array $metadata): void
    {
        $service = 'App\\Services\\AuditService';

        if (class_exists($service) && method_exists($service, 'record')) {
            app($service)->record($userId, $action, $subject, $metadata);

            return;
        }

        Log::info('audit', ['user_id' => $userId, 'action' => $action, 'subject' => $subject::class.'#'.$subject->getKey(), 'metadata' => $metadata]);
    }
}
