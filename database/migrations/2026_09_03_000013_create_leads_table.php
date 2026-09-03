<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.23 leads — contact form + newsletter
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // contact | newsletter
            $table->string('name', 150)->nullable();
            $table->string('email', 191)->index();
            $table->string('phone', 30)->nullable();
            $table->string('order_number', 40)->nullable();
            $table->string('category', 30)->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20);
            $table->text('notes')->nullable();
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
