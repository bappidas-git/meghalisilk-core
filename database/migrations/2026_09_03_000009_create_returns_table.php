<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.18 returns
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 40);
            $table->text('reason_details')->nullable();
            $table->string('status', 25)->default('requested')->index();
            $table->text('reject_reason')->nullable();
            $table->unsignedInteger('refund_amount');
            $table->string('refund_status', 20)->default('pending');
            $table->string('refund_method', 30)->default('original_payment');
            $table->unsignedInteger('deduction_amount')->default(0);
            $table->boolean('restocked')->default(false);
            $table->boolean('store_credit_credited')->default(false);
            $table->string('return_tracking_number', 100)->nullable();
            $table->string('return_tracking_url', 1000)->nullable();
            $table->string('return_carrier', 100)->nullable();
            $table->dateTime('pickup_scheduled_at', 3)->nullable();
            $table->json('images')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('variant_key', 64)->nullable();
            $table->string('variant_name', 150)->nullable();
            $table->string('name', 300);
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal');
        });

        Schema::create('return_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->dateTime('at', 3);
            $table->string('by', 150);
            $table->string('action', 255);
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_status_history');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
