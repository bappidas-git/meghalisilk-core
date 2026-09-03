<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.17 refunds — ledger
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 40)->unique();
            $table->string('type', 30);
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method', 30)->default('original_payment');
            $table->string('reason', 255)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('coupon_restored')->default(false);
            $table->dateTime('initiated_at', 3);
            $table->dateTime('settled_at', 3)->nullable();
            $table->string('by', 150);
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
        });

        // 11.19 wallet_transactions — the store-credit ledger (source of truth)
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // credit | debit
            $table->unsignedInteger('amount');
            $table->string('reason', 255)->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('balance_before');
            $table->unsignedInteger('balance_after');
            $table->dateTime('created_at', 3);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('refunds');
    }
};
