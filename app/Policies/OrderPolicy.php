<?php

namespace App\Policies;

use App\Enums\OrderStatus;
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

    public function markDelivered(User $user, Order $order): bool
    {
        return $user->id === $order->seller_id;
    }

    /** Leave one review of the other party once the order is completed. */
    public function review(User $user, Order $order): bool
    {
        return $order->isParticipant($user)
            && $order->status === OrderStatus::Completed
            && ! $order->reviews()->where('reviewer_id', $user->id)->exists();
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
