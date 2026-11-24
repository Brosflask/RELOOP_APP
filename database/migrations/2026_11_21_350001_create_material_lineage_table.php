<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. Which batch each processed lot came from. Written automatically when
// Developer 3 records processing (listener on MaterialLotsCreated).
// Runs after Developer 3's material_lots (300002) and processing_records (300001).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lineage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('processing_record_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('contributed_weight_kg', 12, 3)->nullable(); // kg of this lot made from this batch
            $table->timestamps();

            $table->unique(['material_lot_id', 'material_batch_id']);
            $table->index('material_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lineage');
    }
};
