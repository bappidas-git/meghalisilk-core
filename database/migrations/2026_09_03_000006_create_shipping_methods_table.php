<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.22 shipping_methods — admin-managed flat / free rates
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('carrier', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('rate_type', 10)->default('flat'); // flat | free
            $table->unsignedInteger('flat_rate')->default(0);
            $table->unsignedInteger('free_above')->nullable();
            $table->string('estimated_days', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
