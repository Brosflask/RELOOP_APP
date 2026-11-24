<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. Artisan products.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('product_category');                       // bags, baskets, furniture, decor, tiles...
            $table->string('product_condition')->default('new');      // new | used
            $table->unsignedInteger('production_quantity');           // pieces made for this listing
            // Cached copy of available pieces, kept in step by InventoryService.
            $table->decimal('available_quantity', 12, 3)->default(0);
            $table->string('unit', 16)->default('piece');
            $table->timestamps();

            $table->index('product_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_details');
    }
};
