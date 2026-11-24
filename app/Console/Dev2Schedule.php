<?php

namespace App\Console;

use App\Jobs\ConfirmStaleCollectionRecords;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Developer 2's scheduled jobs. routes/console.php (Developer 1) calls:
 *
 *     App\Console\Dev2Schedule::register(app(Illuminate\Console\Scheduling\Schedule::class));
 */
final class Dev2Schedule
{
    public static function register(Schedule $schedule): void
    {
        $schedule->job(new ConfirmStaleCollectionRecords)->hourly()->withoutOverlapping()->name('collections:auto-confirm');
    }
}
