<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Enums\OfferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\ListingTerms;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Buyer-seller conversations, messages and structured offers. Accepting an
 * offer hands over to OrderService inside the same transaction, so an
 * accepted offer always has exactly one order.
 */
class MessagingService
{
    /** [GUESS] offers stay open for 72 hours. */
    public const OFFER_LIFETIME_HOURS = 72;

    public function __construct(private readonly OrderService $orders)
    {
    }

    public function openConversation(User $buyer, Listing $listing): Conversation
    {
        $terms = ListingTerms::for($listing);

        if ($terms->sellerId === $buyer->id) {
            throw new BusinessRuleException('You cannot start a conversation about your own listing.', 'listing_id');
        }

        return Conversation::firstOrCreate(
            ['listing_id' => $listing->id, 'buyer_id' => $buyer->id],
            ['seller_id' => $terms->sellerId, 'status' => ConversationStatus::Open],
        );
    }

    public function sendMessage(Conversation $conversation, User $sender, string $body): Message
    {
        $this->assertOpen($conversation);

        return DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => trim($body),
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }

    public function markRead(Conversation $conversation, User $reader): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Make a new offer, or a counter-offer when $parent is given. A
     * conversation has at most one pending offer at a time.
     */
    public function makeOffer(Conversation $conversation, User $maker, string $unitPrice, string $quantity, ?string $message = null, ?Offer $parent = null): Offer
    {
        $this->assertOpen($conversation);

        if (! $conversation->listing_id) {
            throw new BusinessRuleException('Offers need a listing.', 'offer');
        }

        return DB::transaction(function () use ($conversation, $maker, $unitPrice, $quantity, $message, $parent) {
            Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $this->expireStaleOffers($conversation);

            $pending = $conversation->offers()->where('status', OfferStatus::Pending)->first();

            if ($parent) {
                if (! $pending || $pending->id !== $parent->id) {
                    throw new BusinessRuleException('That offer can no longer be countered.', 'offer');
                }
                if ($parent->made_by === $maker->id) {
                    throw new BusinessRuleException('You cannot counter your own offer. Withdraw it instead.', 'offer');
                }
                $parent->update(['status' => OfferStatus::Countered, 'responded_at' => now()]);
            } elseif ($pending) {
                throw new BusinessRuleException(
                    $pending->made_by === $maker->id
                        ? 'You already have an open offer. Withdraw it before making a new one.'
                        : 'There is an open offer from the other party. Accept, reject or counter it.',
                    'offer',
                );
            }

            $listing = Listing::findOrFail($conversation->listing_id);
            $terms = ListingTerms::for($listing);
            if (! $terms->orderable) {
                throw new BusinessRuleException('This listing is not available for ordering.', 'offer');
            }

            $available = app(\App\Contracts\InventoryReservations::class)->availableQuantity($listing);
            if (Money::compareQuantity($quantity, $available) > 0) {
                throw new BusinessRuleException("Only {$available} {$terms->unit} available.", 'quantity');
            }

            $offer = $conversation->offers()->create([
                'listing_id' => $listing->id,
                'made_by' => $maker->id,
                'parent_offer_id' => $parent?->id,
                'unit_price' => Money::fromCents(Money::toCents($unitPrice)),
                'quantity' => Money::quantity($quantity),
                'message' => $message,
                'status' => OfferStatus::Pending,
                'expires_at' => now()->addHours(self::OFFER_LIFETIME_HOURS),
            ]);
            $conversation->update(['last_message_at' => $offer->created_at]);

            return $offer;
        });
    }

    /** Accept an offer from the other party. Creates the order and reserves stock. */
    public function acceptOffer(Offer $offer, User $acceptor): Order
    {
        return DB::transaction(function () use ($offer, $acceptor) {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            $conversation = Conversation::query()->lockForUpdate()->findOrFail($offer->conversation_id);

            $this->assertRespondable($offer, $acceptor);
            $this->assertOpen($conversation);

            $offer->update(['status' => OfferStatus::Accepted, 'responded_at' => now()]);
            $order = $this->orders->createFromOffer($offer, $conversation);
            $conversation->update(['last_message_at' => now()]);

            return $order;
        });
    }

    public function rejectOffer(Offer $offer, User $by): Offer
    {
        return DB::transaction(function () use ($offer, $by) {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            $this->assertRespondable($offer, $by);
            $offer->update(['status' => OfferStatus::Rejected, 'responded_at' => now()]);

            return $offer;
        });
    }

    public function withdrawOffer(Offer $offer, User $by): Offer
    {
        return DB::transaction(function () use ($offer, $by) {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            if ($offer->made_by !== $by->id) {
                throw new BusinessRuleException('Only the person who made an offer can withdraw it.', 'offer');
            }
            if (! $offer->isPending()) {
                throw new BusinessRuleException('This offer is no longer open.', 'offer');
            }
            $offer->update(['status' => OfferStatus::Withdrawn, 'responded_at' => now()]);

            return $offer;
        });
    }

    /** Marks pending offers past their expiry as expired. Returns how many. */
    public function expireStaleOffers(?Conversation $conversation = null): int
    {
        return Offer::query()
            ->when($conversation, fn ($q) => $q->where('conversation_id', $conversation->id))
            ->where('status', OfferStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => OfferStatus::Expired, 'updated_at' => now()]);
    }

    private function assertRespondable(Offer $offer, User $user): void
    {
        if ($offer->made_by === $user->id) {
            throw new BusinessRuleException('You cannot respond to your own offer.', 'offer');
        }
        if (! $offer->isPending()) {
            throw new BusinessRuleException('This offer is no longer open.', 'offer');
        }
    }

    private function assertOpen(Conversation $conversation): void
    {
        if ($conversation->status === ConversationStatus::Closed) {
            throw new BusinessRuleException('This conversation is closed.', 'conversation');
        }
    }
}
