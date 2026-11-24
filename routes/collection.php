<?php

// Developer 2. Required from routes/web.php (Developer 1): require __DIR__.'/collection.php';

use App\Http\Controllers\Collection\AdminCollectionController;
use App\Http\Controllers\Collection\CollectionRecordResponseController;
use App\Http\Controllers\Collection\CollectionRequestController;
use App\Http\Controllers\Collection\CollectorDashboardController;
use App\Http\Controllers\Collection\CollectorJobController;
use App\Http\Controllers\Collection\CollectorRecordController;
use App\Http\Controllers\Collection\HouseholdCollectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:household'])->prefix('household/collections')->name('household.collections.')->group(function () {
    Route::get('/', [HouseholdCollectionController::class, 'index'])->name('index');
    Route::get('/create', [HouseholdCollectionController::class, 'create'])->name('create');
    Route::post('/', [HouseholdCollectionController::class, 'store'])->name('store');
    Route::get('/{collectionRequest}/edit', [HouseholdCollectionController::class, 'edit'])->name('edit');
    Route::put('/{collectionRequest}', [HouseholdCollectionController::class, 'update'])->name('update');
});

// Shared by household, collector and admin. CollectionRequestPolicy decides who sees what.
Route::middleware('auth')->group(function () {
    Route::get('/collections/{collectionRequest}', [CollectionRequestController::class, 'show'])->name('collections.show');
    Route::post('/collections/{collectionRequest}/cancel', [CollectionRequestController::class, 'cancel'])->name('collections.cancel');
    Route::post('/collection-records/{record}/confirm', [CollectionRecordResponseController::class, 'confirm'])->name('collection-records.confirm');
    Route::post('/collection-records/{record}/dispute', [CollectionRecordResponseController::class, 'dispute'])->name('collection-records.dispute');
});

Route::middleware(['auth', 'role:collector'])->prefix('collector')->name('collector.')->group(function () {
    Route::get('/dashboard', CollectorDashboardController::class)->name('dashboard');
    Route::post('/jobs/{collectionRequest}/accept', [CollectorJobController::class, 'accept'])->name('jobs.accept');
    Route::post('/jobs/{collectionRequest}/release', [CollectorJobController::class, 'release'])->name('jobs.release');
    Route::post('/jobs/{collectionRequest}/start', [CollectorJobController::class, 'start'])->name('jobs.start');
    Route::post('/jobs/{collectionRequest}/complete', [CollectorJobController::class, 'complete'])->name('jobs.complete');
    Route::get('/records', [CollectorRecordController::class, 'index'])->name('records.index');
    Route::get('/records/create', [CollectorRecordController::class, 'create'])->name('records.create');
    Route::post('/records', [CollectorRecordController::class, 'store'])->name('records.store');
});

// Kept here (not in routes/admin.php) so Developer 2 doesn't edit Developer 1 and 4's file.
Route::middleware(['auth', 'role:admin'])->prefix('admin/collections')->name('admin.collections.')->group(function () {
    Route::get('/', [AdminCollectionController::class, 'index'])->name('index');
    Route::post('/{collectionRequest}/assign', [AdminCollectionController::class, 'assign'])->name('assign');
    Route::post('/records/{record}/resolve', [AdminCollectionController::class, 'resolve'])->name('resolve');
});
