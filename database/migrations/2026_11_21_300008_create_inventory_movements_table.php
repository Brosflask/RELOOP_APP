<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Developer 3. Append-only stock ledger. Rows are never updated or deleted;
// mistakes are fixed with an adjustment row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            // Stock bucket: a material lot (raw material), or a finished-product listing when lot is null.
            $table->foreignId('material_lot_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->restrictOnDelete();
            // orders / order_items come later (Developer 4, 4000xx): indexed, no FK.
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('movement_type');                          // App\Enums\MovementType
            $table->decimal('quantity_change', 12, 3)->default(0);    // + in, - out (on hand)
            $table->decimal('reserved_change', 12, 3)->default(0);    // + reserved, - released or sold
            $table->string('unit', 16);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['material_lot_id', 'id']);
            $table->index(['listing_id', 'id']);
            $table->index(['order_item_id', 'movement_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
