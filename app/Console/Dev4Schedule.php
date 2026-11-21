<?php

namespace App\Console;

use App\Jobs\CancelUnpaidOrders;
use App\Jobs\CompleteDeliveredOrders;
use App\Jobs\ExpireStaleOffers;
use App\Jobs\ReconcilePendingPayments;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Developer 4's scheduled jobs. routes/console.php (Developer 1) calls:
 *
 *     App\Console\Dev4Schedule::register(app(Illuminate\Console\Scheduling\Schedule::class));
 *
 * The server needs the standard cron entry (php artisan schedule:run every
 * minute) and a queue worker (php artisan queue:work).
 */
final class Dev4Schedule
{
    public static function register(Schedule $schedule): void
    {
        $schedule->job(new ReconcilePendingPayments)->everyFiveMinutes()->withoutOverlapping()->name('payments:reconcile');
        $schedule->job(new ExpireStaleOffers)->hourly()->name('offers:expire');
        $schedule->job(new CancelUnpaidOrders)->hourly()->withoutOverlapping()->name('orders:cancel-unpaid');
        $schedule->job(new CompleteDeliveredOrders)->hourly()->withoutOverlapping()->name('orders:auto-complete');
    }
}
