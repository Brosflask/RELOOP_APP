<?php

namespace App\Providers;

use App\Contracts\InventoryReservations;
use App\Services\InventoryService;
use Illuminate\Support\ServiceProvider;

/**
 * Developer 3. Gives Developer 4's order and payment code the real stock service.
 * Register in bootstrap/providers.php (Developer 1's file, one line).
 */
class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryReservations::class, InventoryService::class);
    }
}
