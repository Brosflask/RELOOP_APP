<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. Developer 4 reads material_lot_id and unit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_material_listing_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('material_lot_id')->constrained()->restrictOnDelete();
            $table->string('material_type');
            // Cached copy of what InventoryService::availableQuantity() returns, for marketplace
            // filters and sorting. The ledger is the source of truth; InventoryService keeps this in step.
            $table->decimal('available_quantity', 12, 3)->default(0);
            $table->decimal('minimum_order_quantity', 12, 3)->nullable();
            $table->string('unit', 16)->default('kg');
            $table->timestamps();

            $table->index('material_lot_id');
            $table->index('material_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_listing_details');
    }
};
