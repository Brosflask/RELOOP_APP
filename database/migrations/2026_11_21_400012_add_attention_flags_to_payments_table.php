<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags payments an admin must look at: an amount mismatch, a second
 * successful payment for an already paid order, or one stuck pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('requires_attention')->default(false)->after('verified_at');
            $table->string('attention_reason', 255)->nullable()->after('requires_attention');
            $table->index(['requires_attention', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['requires_attention', 'status']);
            $table->dropColumn(['requires_attention', 'attention_reason']);
        });
    }
};
