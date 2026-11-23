<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\MaterialLot;
use App\Models\User;

/** Developer 3. Lots are private to their owner (and admins). */
class MaterialLotPolicy
{
    public function view(User $user, MaterialLot $lot): bool
    {
        return $lot->owner_id === $user->id || $user->hasRole(RoleName::Admin);
    }

    public function adjust(User $user, MaterialLot $lot): bool
    {
        return $lot->owner_id === $user->id;
    }
}
