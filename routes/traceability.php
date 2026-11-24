<?php

// Developer 2. Required from routes/web.php (Developer 1): require __DIR__.'/traceability.php';

use App\Http\Controllers\Traceability\BatchController;
use App\Http\Controllers\Traceability\RecyclerBatchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:collector'])->prefix('collector/batches')->name('collector.batches.')->group(function () {
    Route::get('/', [BatchController::class, 'index'])->name('index');
    Route::get('/create', [BatchController::class, 'create'])->name('create');
    Route::post('/', [BatchController::class, 'store'])->name('store');
    Route::post('/consolidate', [BatchController::class, 'consolidate'])->name('consolidate');
    Route::post('/{batch}/records', [BatchController::class, 'addRecords'])->name('records.add');
    Route::delete('/{batch}/records/{record}', [BatchController::class, 'removeRecord'])->name('records.remove');
    Route::post('/{batch}/dispatch', [BatchController::class, 'dispatch'])->name('dispatch');
    Route::post('/{batch}/reopen', [BatchController::class, 'reopen'])->name('reopen');
});

// Names are recycler.batches.* so they sit next to Developer 3's recycler.* routes without clashing.
Route::middleware(['auth', 'role:recycler'])->prefix('recycler/batches')->name('recycler.batches.')->group(function () {
    Route::get('/', [RecyclerBatchController::class, 'index'])->name('index');
    Route::post('/{batch}/receive', [RecyclerBatchController::class, 'receive'])->name('receive');
    Route::post('/{batch}/reject', [RecyclerBatchController::class, 'reject'])->name('reject');
});

// Shared batch page: the collector, the recycler it was sent to, or an admin (MaterialBatchPolicy).
Route::middleware('auth')->get('/batches/{batch}', [BatchController::class, 'show'])->name('batches.show');
