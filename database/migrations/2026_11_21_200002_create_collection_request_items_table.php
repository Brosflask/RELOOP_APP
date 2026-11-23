<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 2. Which plastics the household says it has (estimates only).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_request_id')->constrained()->cascadeOnDelete();
            $table->string('material_type', 20);                     // App\Enums\MaterialType
            $table->decimal('estimated_weight_kg', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['collection_request_id', 'material_type'], 'cr_items_request_material_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_request_items');
    }
};
