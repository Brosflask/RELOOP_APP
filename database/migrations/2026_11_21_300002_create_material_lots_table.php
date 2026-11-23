<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. Processed material. Never the same thing as a material batch.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lots', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                         // LOT-2026-000001
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('processing_record_id')->nullable()->constrained()->restrictOnDelete();
            // A lot bought by an artisan is a child of the seller's lot, so lineage is kept.
            $table->foreignId('parent_lot_id')->nullable()->constrained('material_lots')->restrictOnDelete();
            // order_items is created later (Developer 4, 4000xx), so this is indexed but has no FK.
            $table->unsignedBigInteger('source_order_item_id')->nullable()->index();
            $table->string('material_type');                          // App\Enums\MaterialType
            $table->string('form')->nullable();                       // flakes, pellets, regrind, bales
            $table->string('grade')->nullable();
            $table->decimal('initial_quantity', 12, 3);
            $table->string('unit', 16)->default('kg');
            $table->string('status')->default('available');           // App\Enums\MaterialLotStatus
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index('material_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lots');
    }
};
