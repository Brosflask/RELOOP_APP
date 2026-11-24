<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\MaterialBatch;
use App\Models\User;

/** Developer 2. The service checks status rules; this checks who. */
class MaterialBatchPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Collector);
    }

    /** The collector who made it, the recycler it was sent to, or an admin. */
    public function view(User $user, MaterialBatch $batch): bool
    {
        return $batch->collector_id === $user->id
            || ($batch->recycler_id !== null && $batch->recycler_id === $user->id)
            || $user->hasRole(RoleName::Admin);
    }

    /** add or remove records, merge, dispatch */
    public function manage(User $user, MaterialBatch $batch): bool
    {
        return $batch->collector_id === $user->id && $user->hasRole(RoleName::Collector);
    }
}
