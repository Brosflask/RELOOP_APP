<?php

namespace App\Jobs;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Scheduled every 5 minutes. Re-checks pending payments with MTN, backing
 * off per payment (1, 2, 4 ... up to 60 minutes between checks), so a
 * missed callback never leaves an order stuck.
 */
class ReconcilePendingPayments implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(): void
    {
        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('requires_attention', false)
            ->where('created_at', '<=', now()->subMinute())
            ->orderBy('id')
            ->chunkById(100, function ($payments) {
                foreach ($payments as $payment) {
                    if ($this->isDue($payment)) {
                        VerifyMomoPayment::dispatch($payment->id);
                    }
                }
            });
    }

    private function isDue(Payment $payment): bool
    {
        if ($payment->last_checked_at === null) {
            return true;
        }

        $waitMinutes = min(60, 2 ** min($payment->check_attempts, 6));

        return $payment->last_checked_at->lte(now()->subMinutes($waitMinutes));
    }
}
