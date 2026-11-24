<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CollectionRecord;
use App\Models\User;

/** Developer 2. */
class CollectionRecordPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Collector);
    }

    public function view(User $user, CollectionRecord $record): bool
    {
        return in_array($user->id, [$record->collector_id, $record->household_id], true) || $user->hasRole(RoleName::Admin);
    }

    /** confirm or dispute: only the household the material came from. */
    public function respond(User $user, CollectionRecord $record): bool
    {
        return $record->household_id !== null && $record->household_id === $user->id;
    }

    public function resolve(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
