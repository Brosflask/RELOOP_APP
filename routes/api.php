<?php

/*
| API routes: MTN MoMo callbacks only (agreed architecture). Developer 4.
| No session or CSRF here. The token in the path must match MTN_MOMO_CALLBACK_TOKEN.
*/

use App\Http\Controllers\Payments\MomoCallbackController;
use Illuminate\Support\Facades\Route;

Route::match(['post', 'put'], '/payments/momo/callback/{token}/{reference}', MomoCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payments.momo.callback');
