<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. A recycler confirms it received a batch and weighs it again.
// Developer 3 reads material_batch_id, recycler_id and received_weight_kg
// (App\Services\Recycling\ReceivedBatches). Keep those names.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_batch_id')->unique()->constrained()->restrictOnDelete(); // one receipt per batch
            $table->foreignId('recycler_id')->constrained('users')->restrictOnDelete();
            $table->decimal('expected_weight_kg', 12, 3)->nullable(); // batch total when it was dispatched
            $table->decimal('received_weight_kg', 12, 3);            // weighed at the recycler
            $table->decimal('weight_difference_kg', 12, 3)->nullable(); // received - expected (negative = loss in transit)
            $table->dateTime('received_at')->nullable();
            $table->text('condition_notes')->nullable();
            $table->timestamps();

            $table->index(['recycler_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_receipts');
    }
};
