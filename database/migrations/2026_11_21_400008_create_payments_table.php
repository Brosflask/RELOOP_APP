<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('payer_id')->constrained('users');
            $table->foreignId('payee_id')->constrained('users');
            $table->string('provider', 30);
            $table->uuid('transaction_reference')->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('GHS');
            $table->string('status', 20)->default('pending');
            $table->string('payer_msisdn', 20);
            $table->string('external_id', 64);
            $table->string('failure_reason', 255)->nullable();
            // Set only when status becomes successful: the unique index guarantees an
            // order can never have two successful payments, even under a race.
            $table->foreignId('successful_order_id')->nullable()->unique()->constrained('orders');
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedSmallInteger('check_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
