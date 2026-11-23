<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. One table for raw-material and finished-product listings.
// Developer 4 reads: seller_id, listing_type, title, price (per unit), status.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->string('listing_type');                           // raw_material | finished_product
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);                          // GHS per unit
            $table->char('currency', 3)->default('GHS');
            $table->string('status')->default('draft');               // App\Enums\ListingStatus
            $table->string('location')->nullable();                   // town or region, for marketplace filters
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();                                    // order_items keep pointing at old listings

            $table->index(['status', 'listing_type']);
            $table->index('seller_id');
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
