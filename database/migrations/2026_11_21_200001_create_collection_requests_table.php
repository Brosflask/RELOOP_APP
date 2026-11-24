<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. A household asks for its plastic waste to be picked up.
// Needs users (Developer 1).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();                // CR-2026-000001
            $table->foreignId('household_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('collector_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete(); // admin, or the collector who accepted
            $table->string('status', 20)->default('pending');        // App\Enums\CollectionStatus

            $table->string('pickup_address');
            $table->string('area', 100)->nullable();                 // town or suburb, used to match collectors
            $table->string('gps_address', 20)->nullable();           // Ghana Post GPS, e.g. GA-123-4567
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_phone', 20);
            $table->date('preferred_date')->nullable();
            $table->string('preferred_time_slot', 20)->nullable();   // morning, afternoon, evening
            $table->decimal('estimated_weight_kg', 12, 3)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'status']);
            $table->index(['collector_id', 'status']);
            $table->index(['status', 'area']);
            $table->index(['status', 'preferred_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_requests');
    }
};
