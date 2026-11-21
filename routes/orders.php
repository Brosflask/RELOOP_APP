<?php

/*
| Developer 4: buyer-seller messaging, offers and orders.
| Included from routes/web.php.
*/

use App\Http\Controllers\Messaging\ConversationController;
use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\Messaging\OfferController;
use App\Http\Controllers\Orders\BuyerDashboardController;
use App\Http\Controllers\Orders\ComplaintController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Orders\OrderFulfilmentController;
use App\Http\Controllers\Orders\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/buyer/dashboard', BuyerDashboardController::class)->middleware('role:buyer')->name('buyer.dashboard');

    Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::post('/messages', [ConversationController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}/messages', [MessageController::class, 'store'])->middleware('throttle:60,1')->name('messages.messages.store');
    Route::post('/messages/{conversation}/offers', [OfferController::class, 'store'])->middleware('throttle:30,1')->name('messages.offers.store');

    Route::post('/offers/{offer}/accept', [OfferController::class, 'accept'])->name('offers.accept');
    Route::post('/offers/{offer}/reject', [OfferController::class, 'reject'])->name('offers.reject');
    Route::post('/offers/{offer}/withdraw', [OfferController::class, 'withdraw'])->name('offers.withdraw');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:20,1')->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/ship', [OrderFulfilmentController::class, 'ship'])->name('orders.ship');
    Route::post('/orders/{order}/deliver', [OrderFulfilmentController::class, 'deliver'])->name('orders.deliver');
    Route::post('/orders/{order}/confirm-delivery', [OrderFulfilmentController::class, 'confirm'])->name('orders.confirm-delivery');
    Route::post('/orders/{order}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('orders.reviews.store');

    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->middleware('throttle:10,1')->name('complaints.store');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
});

// Public: reviews a user has received.
Route::get('/users/{user}/reviews', [ReviewController::class, 'index'])->name('users.reviews');
