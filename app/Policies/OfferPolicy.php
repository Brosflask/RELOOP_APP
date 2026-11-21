<?php

namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    /** Accept or reject: only the other participant, never the offer's maker. */
    public function respond(User $user, Offer $offer): bool
    {
        $conversation = $offer->conversation;

        return $conversation->isParticipant($user) && $offer->made_by !== $user->id;
    }

    public function withdraw(User $user, Offer $offer): bool
    {
        return $offer->made_by === $user->id;
    }
}
