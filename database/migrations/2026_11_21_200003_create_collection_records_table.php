<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. What a collector actually picked up, weighed on the spot.
// One row per material type. Developer 4's admin report sums actual_weight_kg.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();                // COL-2026-000001
            $table->foreignId('collection_request_id')->nullable()->constrained()->restrictOnDelete(); // null = walk-in drop-off
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('users')->restrictOnDelete();   // copied from the request
            $table->string('material_type', 20);                     // App\Enums\MaterialType
            $table->decimal('actual_weight_kg', 12, 3);
            $table->dateTime('collected_at');
            $table->string('status', 30)->default('pending_confirmation'); // App\Enums\CollectionRecordStatus
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->string('photo_path')->nullable();                // optional photo of the weighed bags
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['collector_id', 'status']);
            $table->index(['household_id', 'status']);
            $table->index('collection_request_id');
            $table->index(['material_type', 'status']);
            $table->index('created_at');                             // admin "kg collected" report
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_records');
    }
};
