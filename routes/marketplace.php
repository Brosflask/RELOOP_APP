<?php

// Developer 3. Required from routes/web.php (Developer 1): require __DIR__.'/marketplace.php';

use App\Http\Controllers\Marketplace\ArtisanDashboardController;
use App\Http\Controllers\Marketplace\ListingImageController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Marketplace\SellerListingController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
Route::get('/marketplace/{listing}', [MarketplaceController::class, 'show'])->name('marketplace.show');

// Sellers (recyclers list raw material, artisans list products; ListingPolicy decides)
Route::middleware('auth')->group(function () {
    Route::get('/my-listings', [SellerListingController::class, 'index'])->name('listings.index');
    Route::get('/my-listings/create', [SellerListingController::class, 'create'])->name('listings.create');
    Route::post('/my-listings/raw-material', [SellerListingController::class, 'storeRawMaterial'])->name('listings.store-raw-material');
    Route::post('/my-listings/product', [SellerListingController::class, 'storeProduct'])->name('listings.store-product');
    Route::get('/my-listings/{listing}/edit', [SellerListingController::class, 'edit'])->name('listings.edit');
    Route::put('/my-listings/{listing}', [SellerListingController::class, 'update'])->name('listings.update');
    Route::patch('/my-listings/{listing}/status', [SellerListingController::class, 'updateStatus'])->name('listings.status');
    Route::post('/my-listings/{listing}/images', [ListingImageController::class, 'store'])->name('listings.images.store');
    Route::delete('/my-listings/{listing}/images/{image}', [ListingImageController::class, 'destroy'])->name('listings.images.destroy');
});

Route::middleware(['auth', 'role:artisan'])->get('/artisan/dashboard', ArtisanDashboardController::class)->name('artisan.dashboard');
