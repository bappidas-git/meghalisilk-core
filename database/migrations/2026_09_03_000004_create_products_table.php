<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 11.6 products (scalar part; images/variants/links are child tables)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 191)->unique();
            $table->string('sku', 100)->nullable()->index();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('brand', 150)->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('compare_price')->default(0);
            $table->unsignedInteger('cost_price')->default(0);
            $table->integer('stock')->default(0);
            $table->integer('low_stock_threshold')->default(10);
            $table->decimal('weight', 8, 3)->default(0);
            $table->decimal('dim_length', 8, 2)->nullable();
            $table->decimal('dim_width', 8, 2)->nullable();
            $table->decimal('dim_height', 8, 2)->nullable();
            $table->json('tags')->nullable();
            $table->boolean('featured')->default(false)->index();
            $table->boolean('trending')->default(false)->index();
            $table->boolean('hot')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('total_reviews')->default(0);
            $table->string('meta_title', 255)->nullable();
            $table->text('meta_description')->nullable();
            $table->dateTime('created_at', 3)->nullable();
            $table->dateTime('updated_at', 3)->nullable();
            $table->dateTime('deleted_at', 3)->nullable();
        });

        // 11.7 product_images
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('url', 1000);
            $table->integer('sort_order')->default(0);
        });

        // 11.8 product_variants — `variant_key` is the string id the frontend uses ("v1")
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('variant_key', 64);
            $table->string('name', 150);
            $table->unsignedInteger('price')->default(0);
            $table->integer('stock')->default(0);
            $table->string('sku', 100)->nullable();
            $table->json('attributes')->nullable();
            $table->string('swatch_hex', 9)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unique(['product_id', 'variant_key']);
        });

        // 11.9 product_links — relatedProductIds / frequentlyBoughtTogetherIds
        Schema::create('product_links', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('linked_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 10); // related | fbt
            $table->integer('sort_order')->default(0);
            $table->primary(['product_id', 'linked_product_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_links');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
    }
};
