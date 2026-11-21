<?php

namespace App\Services;

use App\Contracts\InventoryReservations;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\ListingTerms;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates orders from agreed terms and owns the order state machine.
 * Every status change goes through transition().
 */
class OrderService
{
    public function __construct(private readonly InventoryReservations $inventory)
    {
    }

    /** "Buy now" at the listing's own price, with no negotiation. */
    public function buyAtListedPrice(Listing $listing, User $buyer, string $quantity, ?string $deliveryAddress = null, ?string $deliveryNotes = null): Order
    {
        return DB::transaction(function () use ($listing, $buyer, $quantity, $deliveryAddress, $deliveryNotes) {
            $listing = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            $terms = ListingTerms::for($listing);

            return $this->createOrder($listing, $terms, $buyer, $terms->unitPrice, $quantity, null, null, $deliveryAddress, $deliveryNotes);
        });
    }

    /**
     * Create the order for an accepted offer. Must be called inside the
     * transaction that marks the offer accepted (see MessagingService).
     */
    public function createFromOffer(Offer $offer, Conversation $conversation): Order
    {
        $listing = Listing::query()->lockForUpdate()->findOrFail($offer->listing_id);
        $terms = ListingTerms::for($listing);
        $buyer = User::findOrFail($conversation->buyer_id);

        return $this->createOrder($listing, $terms, $buyer, (string) $offer->unit_price, (string) $offer->quantity, $offer, $conversation, null, null);
    }

    private function createOrder(
        Listing $listing,
        ListingTerms $terms,
        User $buyer,
        string $unitPrice,
        string $quantity,
        ?Offer $offer,
        ?Conversation $conversation,
        ?string $deliveryAddress,
        ?string $deliveryNotes,
    ): Order {
        if ($terms->sellerId === $buyer->id) {
            throw new BusinessRuleException('You cannot buy your own listing.', 'listing_id');
        }
        if (! $terms->orderable) {
            throw new BusinessRuleException('This listing is not available for ordering.', 'listing_id');
        }
        if (Money::toMilli($quantity) <= 0) {
            throw new BusinessRuleException('Quantity must be greater than zero.', 'quantity');
        }
        if (Money::compareQuantity($quantity, $this->inventory->availableQuantity($listing)) > 0) {
            throw new BusinessRuleException('Only '.$this->inventory->availableQuantity($listing).' '.$terms->unit.' available.', 'quantity');
        }

        $lineTotal = Money::lineTotal($unitPrice, $quantity);
        $now = now();

        $order = Order::create([
            'code' => 'TMP-'.Str::uuid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $terms->sellerId,
            'conversation_id' => $conversation?->id,
            'status' => OrderStatus::AwaitingPayment,
            'subtotal' => $lineTotal,
            'total_amount' => $lineTotal,
            'currency' => 'GHS',
            'delivery_address' => $deliveryAddress,
            'delivery_notes' => $deliveryNotes,
        ]);
        $order->update(['code' => sprintf('ORD-%s-%06d', $now->format('Y'), $order->id)]);

        $order->items()->create([
            'listing_id' => $terms->listingId,
            'listing_type' => $terms->listingType,
            'title' => $terms->title,
            'material_lot_id' => $terms->materialLotId,
            'quantity' => Money::quantity($quantity),
            'unit' => $terms->unit,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);

        $order->agreement()->create([
            'offer_id' => $offer?->id,
            'unit_price' => $unitPrice,
            'quantity' => Money::quantity($quantity),
            'total_amount' => $lineTotal,
            'payment_arrangement' => 'momo_collection',
            'buyer_accepted_at' => $now,
            'seller_accepted_at' => $now,
        ]);

        $order->statusHistories()->create([
            'from_status' => null,
            'to_status' => OrderStatus::AwaitingPayment,
            'changed_by' => $buyer->id,
            'note' => $offer ? 'Offer #'.$offer->id.' accepted' : 'Bought at listed price',
        ]);

        $this->inventory->reserve($order->load('items'));

        return $order;
    }

    /**
     * The only way an order's status changes. Locks the order row, rejects
     * transitions the state machine does not allow, stamps the matching
     * timestamp and writes a history row.
     */
    public function transition(Order $order, OrderStatus $to, ?User $by = null, ?string $note = null, array $attributes = []): Order
    {
        return DB::transaction(function () use ($order, $to, $by, $note, $attributes) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new BusinessRuleException("An order that is {$from->label()} cannot become {$to->label()}.", 'status');
            }

            $stamp = match ($to) {
                OrderStatus::Paid => 'paid_at',
                OrderStatus::Shipped => 'shipped_at',
                OrderStatus::Delivered => 'delivered_at',
                OrderStatus::Completed => 'completed_at',
                OrderStatus::Cancelled => 'cancelled_at',
                default => null,
            };

            $locked->fill($attributes);
            $locked->status = $to;
            if ($stamp) {
                $locked->{$stamp} = now();
            }
            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'changed_by' => $by?->id,
                'note' => $note,
            ]);

            if ($to === OrderStatus::Cancelled && $from === OrderStatus::AwaitingPayment) {
                $this->inventory->release($locked->load('items'));
            }

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }

    public function cancel(Order $order, User $by, ?string $reason = null): Order
    {
        return $this->transition($order, OrderStatus::Cancelled, $by, $reason, ['cancellation_reason' => $reason]);
    }

    public function ship(Order $order, User $seller, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Shipped, $seller, $note);
    }

    /** Seller records the hand-over. The buyer can still confirm or raise a complaint. */
    public function markDelivered(Order $order, User $seller, ?string $note = null): Order
    {
        return $this->transition($order, OrderStatus::Delivered, $seller, $note);
    }

    /** Buyer confirms receipt: the order is delivered (if not already) and completed. */
    public function confirmDelivery(Order $order, User $buyer): Order
    {
        return DB::transaction(function () use ($order, $buyer) {
            if ($order->fresh()->status === OrderStatus::Shipped) {
                $this->transition($order, OrderStatus::Delivered, $buyer, 'Buyer confirmed receipt');
            }

            return $this->transition($order, OrderStatus::Completed, $buyer, 'Buyer confirmed receipt');
        });
    }

    /** Raising a complaint on a paid, shipped or delivered order freezes it as disputed. */
    public function dispute(Order $order, User $by, string $note): Order
    {
        return $this->transition($order, OrderStatus::Disputed, $by, $note);
    }
}
