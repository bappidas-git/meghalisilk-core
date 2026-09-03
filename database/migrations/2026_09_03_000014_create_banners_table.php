<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.24 banners — hero slides
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('subtitle')->nullable();
            $table->string('eyebrow', 150)->nullable();
            $table->string('cta', 100)->nullable();
            $table->string('link', 500)->default('/products');
            $table->string('secondary_cta_label', 100)->nullable();
            $table->string('secondary_cta_link', 500)->nullable();
            $table->string('background_type', 10)->default('gradient'); // gradient | image | video
            $table->string('gradient', 500)->nullable();
            $table->string('image', 1000)->nullable();
            $table->string('image_position', 30)->default('right center');
            $table->string('video_url', 1000)->nullable();
            $table->string('video_poster', 1000)->nullable();
            $table->unsignedTinyInteger('overlay_opacity')->nullable();
            $table->string('text_align', 10)->default('left');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
