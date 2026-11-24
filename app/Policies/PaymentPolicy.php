<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $user->id === $payment->payer_id
            || $user->id === $payment->payee_id
            || $user->hasRole(RoleName::Admin);
    }

    /** Confirm or reject a manual payment: the seller who should have received it, or an admin. */
    public function settleManual(User $user, Payment $payment): bool
    {
        return $user->id === $payment->payee_id || $user->hasRole(RoleName::Admin);
    }

    /** Manual "re-check with MTN" from the admin transactions page. */
    public function recheck(User $user, Payment $payment): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
