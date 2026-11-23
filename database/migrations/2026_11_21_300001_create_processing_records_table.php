<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. Needs users (Developer 1) and material_batches (Developer 2) to exist first.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processing_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();                    // PR-2026-000001
            $table->foreignId('recycler_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('material_batch_id')->constrained('material_batches')->restrictOnDelete();
            $table->string('process_type');                           // sorting, washing, shredding, pelletising
            $table->decimal('input_weight_kg', 12, 3);
            $table->decimal('output_weight_kg', 12, 3);
            $table->decimal('loss_weight_kg', 12, 3);                 // input - output, worked out by the server
            $table->dateTime('processed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['recycler_id', 'processed_at']);
            $table->index('material_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_records');
    }
};
