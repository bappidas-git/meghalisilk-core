<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.26 settings — key/JSON: store, shipping, payment, notifications, seo, social, hero_config, deals_config
        Schema::create('settings', function (Blueprint $table) {
            $table->string('section', 40)->primary();
            $table->json('data');
            $table->dateTime('updated_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
