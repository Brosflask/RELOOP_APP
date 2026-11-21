<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function view(User $user, Complaint $complaint): bool
    {
        return $user->id === $complaint->complainant_id || $user->hasRole(RoleName::Admin);
    }

    public function resolve(User $user, Complaint $complaint): bool
    {
        return $user->hasRole(RoleName::Admin);
    }
}
