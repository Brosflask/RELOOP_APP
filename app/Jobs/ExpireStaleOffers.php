<?php

namespace App\Jobs;

use App\Services\MessagingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hourly. Marks pending offers past their expiry as expired. */
class ExpireStaleOffers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function handle(MessagingService $messaging): void
    {
        $messaging->expireStaleOffers();
    }
}
