<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. Collected waste grouped for delivery to a recycler.
// Not the same thing as a material lot (Developer 3's processed output).
// Must run before Developer 3's processing_records (2026_11_21_300001).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                     // BATCH-2026-000001
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->string('material_type', 20);                     // one type per batch, App\Enums\MaterialType
            $table->decimal('total_weight_kg', 12, 3)->default(0);   // sum of its records (or merged batches)
            $table->string('status', 20)->default('open');           // App\Enums\BatchStatus
            $table->foreignId('recycler_id')->nullable()->constrained('users')->restrictOnDelete(); // where it is sent
            $table->foreignId('consolidated_into_id')->nullable()->constrained('material_batches')->restrictOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['collector_id', 'status']);
            $table->index(['recycler_id', 'status']);
            $table->index(['material_type', 'status']);
            $table->index('consolidated_into_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_batches');
    }
};
