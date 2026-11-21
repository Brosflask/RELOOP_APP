<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Asks MTN for the status of one payment and applies it. Safe to run any number of times. */
class VerifyMomoPayment implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 60;

    public function __construct(public int $paymentId)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->paymentId;
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PaymentService $payments): void
    {
        $payment = Payment::find($this->paymentId);

        if ($payment) {
            $payments->verify($payment);
        }
    }
}
