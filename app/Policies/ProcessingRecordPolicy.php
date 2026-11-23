<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ProcessingRecord;
use App\Models\User;

/** Developer 3. */
class ProcessingRecordPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('recycler');
    }

    public function view(User $user, ProcessingRecord $record): bool
    {
        return $record->recycler_id === $user->id || $user->hasRole(RoleName::Admin);
    }
}
