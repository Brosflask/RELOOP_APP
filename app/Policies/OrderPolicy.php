<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->isParticipant($user) || $user->hasRole(RoleName::Admin);
    }

    /** Status rules (e.g. only before payment) are enforced by OrderService. */
    public function cancel(User $user, Order $order): bool
    {
        return $order->isParticipant($user);
    }

    public function ship(User $user, Order $order): bool
    {
        return $user->id === $order->seller_id;
    }

    public function confirmDelivery(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_id;
    }

    public function pay(User $user, Order $order): bool
    {
        return $user->id === $order->buyer_id;
    }
}
