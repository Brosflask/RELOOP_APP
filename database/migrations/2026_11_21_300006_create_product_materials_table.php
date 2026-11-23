<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. Which material lots a finished product was made from (traceability).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_used', 12, 3)->nullable();
            $table->string('unit', 16)->default('kg');
            $table->timestamps();

            $table->unique(['listing_id', 'material_lot_id']);
            $table->index('material_lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_materials');
    }
};
