<?php

/*
| Admin routes. Shared file: Developer 1 owns admin user management and
| verification routes; Developer 4 owns the section below. Keep sections
| separate to avoid merge conflicts. Included from routes/web.php.
*/

use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReviewModerationController;
use App\Http\Controllers\Admin\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Developer 1: users, roles, verification (add here).

    // Developer 4: reports, transactions, complaints, disputes, reviews.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/transactions.csv', [ReportController::class, 'exportTransactions'])->name('reports.transactions.export');

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/{payment}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{payment}/recheck', [TransactionController::class, 'recheck'])->middleware('throttle:20,1')->name('transactions.recheck');
    Route::post('/transactions/{payment}/acknowledge', [TransactionController::class, 'acknowledge'])->name('transactions.acknowledge');

    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::patch('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');

    Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes.index');
    Route::post('/disputes/{order}/resolve', [DisputeController::class, 'resolve'])->name('disputes.resolve');

    Route::get('/reviews', [ReviewModerationController::class, 'index'])->name('reviews.index');
    Route::patch('/reviews/{review}', [ReviewModerationController::class, 'update'])->name('reviews.update');
});
