<?php 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Link with users table
            $table->string('order_id')->unique(); // Razorpay order_id
            $table->string('payment_id')->nullable(); // Razorpay payment_id
            $table->string('signature')->nullable(); // Razorpay signature
            $table->decimal('amount', 10, 2); // Amount in INR
            $table->enum('status', ['created', 'paid', 'failed'])->default('created');
            $table->json('payload')->nullable(); // extra info from webhook/response
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('payments');
    }
};
