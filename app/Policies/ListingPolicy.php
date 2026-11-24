<?php

namespace App\Policies;

use App\Enums\ListingStatus;
use App\Enums\RoleName;
use App\Models\Listing;
use App\Models\User;

/** Developer 3. */
class ListingPolicy
{
    /** Live listings are public; drafts and closed ones only for the seller and admins. */
    public function view(?User $user, Listing $listing): bool
    {
        if (in_array($listing->status, [ListingStatus::Active, ListingStatus::SoldOut, ListingStatus::Paused], true)) {
            return true;
        }

        return $user !== null && ($user->id === $listing->seller_id || $user->hasRole(RoleName::Admin));
    }

    public function createRawMaterial(User $user): bool
    {
        return $user->hasRole('recycler');
    }

    public function createProduct(User $user): bool
    {
        return $user->hasRole('artisan');
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->id === $listing->seller_id;
    }
}
