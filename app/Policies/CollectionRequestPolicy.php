<?php

namespace App\Policies;

use App\Enums\CollectionStatus;
use App\Enums\RoleName;
use App\Models\CollectionRequest;
use App\Models\User;

/** Developer 2. Who can see and act on a pickup request. The service checks status rules. */
class CollectionRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Household);
    }

    public function view(User $user, CollectionRequest $request): bool
    {
        return $request->household_id === $user->id
            || ($request->collector_id !== null && $request->collector_id === $user->id)
            || ($request->status === CollectionStatus::Pending && $user->hasRole(RoleName::Collector))
            || $user->hasRole(RoleName::Admin);
    }

    public function update(User $user, CollectionRequest $request): bool
    {
        return $request->household_id === $user->id;
    }

    public function cancel(User $user, CollectionRequest $request): bool
    {
        return $request->household_id === $user->id || $user->hasRole(RoleName::Admin);
    }

    public function accept(User $user, CollectionRequest $request): bool
    {
        return $user->hasRole(RoleName::Collector) && $request->household_id !== $user->id;
    }

    /** release, start and complete: only the collector it is assigned to. */
    public function work(User $user, CollectionRequest $request): bool
    {
        return $request->collector_id === $user->id && $user->hasRole(RoleName::Collector);
    }

    public function assign(User $user): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
