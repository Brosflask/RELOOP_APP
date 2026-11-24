<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\ManualPaymentSubmitted;
use App\Events\OfferMade;
use App\Events\OrderStatusChanged;
use App\Events\PaymentFailed;
use App\Models\User;
use App\Notifications\OfferReceived;
use App\Notifications\OrderUpdated;

/**
 * Tells buyers and sellers what happened. The person who acted is not notified.
 * Registered by Laravel's event discovery (handle* methods with typed events).
 */
class SendOrderNotifications
{
    public function handleStatusChanged(OrderStatusChanged $event): void
    {
        $order = $event->order;
        [$title, $toBuyer, $toSeller] = match ($event->to) {
            OrderStatus::AwaitingPayment => ['Order created', 'Your order is ready for payment.', 'You have a new order. It is waiting for the buyer\'s payment.'],
            OrderStatus::Paid => ['Payment confirmed', 'MTN confirmed your payment. The seller will prepare your order.', 'Payment confirmed by MTN. Please ship the order.'],
            OrderStatus::Shipped => ['Order shipped', 'Your order is on the way.', null],
            OrderStatus::Delivered => ['Order delivered', 'The seller marked your order as delivered. Please confirm you received it.', null],
            OrderStatus::Completed => ['Order completed', 'Your order is complete. You can now leave a review.', 'The order is complete. You can now leave a review.'],
            OrderStatus::Cancelled => ['Order cancelled', 'Your order was cancelled.', 'The order was cancelled.'],
            OrderStatus::Disputed => ['Order on hold', 'The order is on hold while an admin reviews a complaint.', 'The order is on hold while an admin reviews a complaint.'],
        };

        $this->notify($order->buyer, $event->by, $toBuyer, fn ($body) => new OrderUpdated($order, $title, $body));
        $this->notify($order->seller, $event->by, $toSeller, fn ($body) => new OrderUpdated($order, $title, $body));
    }

    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $order = $event->payment->order;
        $order->buyer->notify(new OrderUpdated($order, 'Payment not completed', 'Your MoMo payment did not go through. You can try again from the order page.'));
    }

    public function handleManualPaymentSubmitted(ManualPaymentSubmitted $event): void
    {
        $payment = $event->payment->loadMissing('order.seller');
        $payment->order->seller->notify(new OrderUpdated(
            $payment->order,
            'Confirm a payment',
            sprintf('The buyer says they paid GHS %s (reference %s). Check that you received it, then confirm or reject it on the order page.', $payment->amount, $payment->provider_reference),
        ));
    }

    public function handleOfferMade(OfferMade $event): void
    {
        $offer = $event->offer->loadMissing('conversation', 'maker');
        $recipientId = $offer->conversation->otherParticipantId($offer->maker);
        User::find($recipientId)?->notify(new OfferReceived($offer));
    }

    private function notify(?User $user, ?User $actor, ?string $body, callable $make): void
    {
        if ($user && $body && $user->id !== $actor?->id) {
            $user->notify($make($body));
        }
    }
}
