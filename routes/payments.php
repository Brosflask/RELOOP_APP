<?php

/*
| Developer 4: MTN MoMo payments (web, session-authenticated).
| Included from routes/web.php. The MTN callback lives in routes/api.php.
*/

use App\Http\Controllers\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/orders/{order}/pay', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/orders/{order}/payments', [PaymentController::class, 'store'])->middleware('throttle:10,1')->name('payments.store');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::post('/payments/{payment}/confirm', [PaymentController::class, 'confirm'])->name('payments.confirm');
    Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    Route::get('/payments/{payment}/status', [PaymentController::class, 'status'])->middleware('throttle:60,1')->name('payments.status');
});
