<?php

namespace App\Jobs;

use App\Services\CollectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Developer 2. Confirms records a household didn't answer within CollectionService::AUTO_CONFIRM_AFTER_HOURS. */
class ConfirmStaleCollectionRecords implements ShouldQueue
{
    use Queueable;

    public function handle(CollectionService $collections): void
    {
        $collections->autoConfirmStale();
    }
}
