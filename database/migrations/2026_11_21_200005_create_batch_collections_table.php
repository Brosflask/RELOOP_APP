<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. Which collection records went into which batch.
// A record can be in only one batch (unique collection_record_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('collection_record_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('weight_kg', 12, 3);                     // copy of the record weight when it was added
            $table->timestamps();

            $table->index('material_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_collections');
    }
};
