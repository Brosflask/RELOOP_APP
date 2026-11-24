<?php

// Developer 3. Required from routes/web.php (Developer 1): require __DIR__.'/recycling.php';

use App\Http\Controllers\Recycling\InventoryAdjustmentController;
use App\Http\Controllers\Recycling\MaterialLotController;
use App\Http\Controllers\Recycling\ProcessingRecordController;
use App\Http\Controllers\Recycling\RecyclerDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:recycler'])->prefix('recycler')->name('recycler.')->group(function () {
    Route::get('/dashboard', RecyclerDashboardController::class)->name('dashboard');
    Route::get('/processing', [ProcessingRecordController::class, 'index'])->name('processing.index');
    Route::get('/processing/create', [ProcessingRecordController::class, 'create'])->name('processing.create');
    Route::post('/processing', [ProcessingRecordController::class, 'store'])->name('processing.store');
    Route::get('/processing/{record}', [ProcessingRecordController::class, 'show'])->name('processing.show');
});

// Any signed-in owner: recyclers see the lots they produced, artisans the lots they bought.
// MaterialLotPolicy keeps each lot private to its owner.
Route::middleware('auth')->group(function () {
    Route::get('/lots', [MaterialLotController::class, 'index'])->name('lots.index');
    Route::get('/lots/{lot}', [MaterialLotController::class, 'show'])->name('lots.show');
    Route::post('/lots/{lot}/adjustments', [InventoryAdjustmentController::class, 'store'])->name('lots.adjust');
});
