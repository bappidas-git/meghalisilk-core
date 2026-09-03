<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.15 payments — one row per order
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->char('currency', 3)->default('INR');
            $table->string('payment_method', 20);
            $table->string('gateway', 30);
            $table->string('transaction_id', 100)->nullable()->index();
            $table->string('gateway_order_id', 100)->nullable();
            $table->string('status', 25)->default('pending')->index();
            $table->json('gateway_response')->nullable();
            $table->unsignedInteger('refund_amount')->default(0);
            $table->string('refund_reason', 255)->nullable();
            $table->json('pending_refund')->nullable();
            $table->unsignedInteger('store_credit_applied')->default(0);
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
        });

        // 11.16 payment_refunds — payments[].refunds[]
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('ref_key', 40);
            $table->unsignedInteger('amount');
            $table->string('reason', 255)->nullable();
            $table->dateTime('at', 3);
            $table->string('by', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payments');
    }
};
