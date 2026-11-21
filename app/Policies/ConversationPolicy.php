<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /** Participants read their threads; admins may read any thread (read-only, for disputes). */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user) || $user->hasRole(RoleName::Admin);
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    public function makeOffer(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }
}
