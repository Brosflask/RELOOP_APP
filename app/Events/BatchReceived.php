<?php

namespace App\Events;

use App\Models\BatchReceipt;
use App\Models\MaterialBatch;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Developer 2. Fired when a recycler confirms receipt of a batch.
 * Anyone (for example Developer 3's recycler dashboard) can listen to it.
 */
class BatchReceived
{
    use Dispatchable;

    public function __construct(public readonly MaterialBatch $batch, public readonly BatchReceipt $receipt) {}
}
