<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.12 orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 50)->nullable();
            $table->boolean('coupon_restored')->default(false);
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('shipping_amount')->default(0);
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('tax_amount')->default(0);
            $table->unsignedInteger('cod_fee')->default(0);
            $table->unsignedInteger('total');
            $table->unsignedInteger('store_credit_used')->default(0);
            $table->boolean('store_credit_returned')->default(false);
            $table->unsignedInteger('amount_payable');
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('fulfillment_status', 25)->default('unfulfilled')->index();
            $table->string('shipping_status', 20)->default('pending');
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 1000)->nullable();
            $table->string('shiprocket_order_id', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('shipping_address');
            $table->json('billing_address');
            $table->text('cancel_reason')->nullable();
            $table->dateTime('cancelled_at', 3)->nullable();
            $table->dateTime('delivered_at', 3)->nullable();
            $table->string('refund_status', 20)->nullable();
            $table->string('refund_method', 30)->nullable();
            $table->unsignedInteger('refunded_amount')->default(0);
            $table->dateTime('refund_completed_at', 3)->nullable();
            $table->json('pending_refund')->nullable();
            $table->json('recall')->nullable();
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
            $table->index(['user_id', 'created_at']);
            $table->index(['coupon_code', 'user_id']);
        });

        // 11.13 order_items
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('variant_key', 64)->nullable();
            $table->string('variant_name', 150)->nullable();
            $table->string('name', 300);
            $table->string('image', 1000)->nullable();
            $table->string('sku', 100)->nullable();
            $table->unsignedInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('subtotal');
        });

        // 11.14 order_status_history — audit timeline
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->dateTime('at', 3);
            $table->string('by', 150);
            $table->string('action', 255);
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
