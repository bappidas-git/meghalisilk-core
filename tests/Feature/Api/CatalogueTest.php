<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;

class CatalogueTest extends ApiTestCase
{
    public function test_products_list_hides_drafts_and_cost_price(): void
    {
        Product::query()->whereKey(19)->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/products')->assertOk()->assertJsonPath('success', true);
        $products = $response->json('data');

        $this->assertCount(5, $products);
        $this->assertSame([1, 6, 10, 13, 16], array_column($products, 'id'));
        $first = $products[0];
        foreach (['id', 'name', 'slug', 'price', 'images', 'variants', 'isActive', 'categoryId', 'rating', 'totalReviews', 'relatedProductIds', 'frequentlyBoughtTogetherIds', 'dimensions', 'tags'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }
        $this->assertArrayNotHasKey('costPrice', $first);
        $this->assertSame('v1', $first['variants'][0]['id']);
        $this->assertSame(32500, $first['variants'][0]['price']); // corrupted seed value cleaned on import
        $this->assertSame(38000, $first['comparePrice']);
        $this->assertSame(['length' => 38, 'width' => 28, 'height' => 8], $first['dimensions']);
        $this->assertSame([13, 6, 10], $first['relatedProductIds']); // dangling ids dropped
        $this->assertSame([19], $first['frequentlyBoughtTogetherIds']);

        $this->getJson('/api/v1/products/19')->assertStatus(404)->assertJsonPath('message', 'Not found');
        $this->getJson('/api/v1/products/slug/eri-silk-shawl-undyed-ivory')->assertStatus(404);
    }

    public function test_product_lookups_search_and_lists(): void
    {
        $this->getJson('/api/v1/products/1')->assertOk()->assertJsonPath('data.slug', 'sualkuchi-muga-mekhela-chador-natural-gold');
        $this->getJson('/api/v1/products/slug/muga-silk-saree-assam-golden')->assertOk()->assertJsonPath('data.id', 13);

        $ids = array_column($this->getJson('/api/v1/products?search=MUGA')->assertOk()->json('data'), 'id');
        $this->assertContains(1, $ids);
        $this->assertContains(13, $ids);
        $this->assertNotContains(19, $ids);

        $this->assertCount(2, $this->getJson('/api/v1/products/featured?limit=2')->assertOk()->json('data'));
        $trending = $this->getJson('/api/v1/products/trending?limit=8')->assertOk()->json('data');
        foreach ($trending as $p) {
            $this->assertTrue($p['trending']);
        }
    }

    public function test_products_by_category_includes_descendants(): void
    {
        $child = Category::create(['name' => 'Muga Sarees', 'slug' => 'muga-sarees', 'parent_id' => 1, 'is_active' => true]);
        Product::query()->whereKey(13)->update(['category_id' => $child->id]);

        $ids = array_column($this->getJson('/api/v1/products/category/1')->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([1, 13], $ids);
    }

    public function test_product_reviews_only_approved(): void
    {
        $reviews = $this->getJson('/api/v1/products/1/reviews')->assertOk()->json('data');
        $this->assertNotEmpty($reviews);
        foreach ($reviews as $review) {
            $this->assertSame('approved', $review['status']);
            $this->assertSame(1, $review['productId']);
        }
        $this->getJson('/api/v1/products/999/reviews')->assertStatus(404);
    }

    public function test_categories_banners_hero_settings_faqs_deals(): void
    {
        $categories = $this->getJson('/api/v1/categories')->assertOk()->json('data');
        $this->assertCount(3, $categories);
        foreach (['id', 'name', 'slug', 'parentId', 'isActive', 'sortOrder', 'showInMainMenu', 'menuOrder'] as $key) {
            $this->assertArrayHasKey($key, $categories[0]);
        }
        $this->getJson('/api/v1/categories/1')->assertOk()->assertJsonPath('data.slug', 'muga-silk');
        $this->getJson('/api/v1/categories/slug/muga-silk')->assertOk()->assertJsonPath('data.id', 1);
        $this->getJson('/api/v1/categories/999')->assertStatus(404);

        $banners = $this->getJson('/api/v1/banners')->assertOk()->json('data');
        $this->assertNotEmpty($banners);
        $this->assertSame('', $banners[0]['eyebrow']);

        $this->getJson('/api/v1/hero/config')->assertOk()
            ->assertJsonPath('data.intervalMs', 1500)
            ->assertJsonPath('data.heights.desktop.min', 520)
            ->assertJsonPath('data.updatedAt', '2026-09-01T12:31:51.228Z');

        $settings = $this->getJson('/api/v1/settings')->assertOk()->json('data');
        $this->assertSame(['store', 'payment', 'social'], array_keys($settings));
        $this->assertSame(5, $settings['store']['taxRate']);
        $this->assertStringNotContainsString('shiprocketPassword', json_encode($settings));

        $faqs = $this->getJson('/api/v1/faqs')->assertOk()->json('data');
        $this->assertSame('How should I care for Muga, Pat and Eri silk?', $faqs[0]['question']);
        $this->assertStringEndsNotWith('bhbhhjh', $faqs[0]['answer']);
        $this->assertArrayHasKey('productIds', $faqs[0]);

        $this->getJson('/api/v1/deals/config')->assertOk()
            ->assertJsonPath('data.dealOfTheDayIds', [1, 13])
            ->assertJsonPath('data.featuredProductIds', [1, 6, 13, 16])
            ->assertJsonPath('data.featuredCouponIds', [2, 3, 7]);

        $methods = $this->getJson('/api/v1/shipping/methods')->assertOk()->json('data');
        foreach ($methods as $m) {
            $this->assertTrue($m['isActive']);
        }
        $this->assertCount(4, $methods);

        $coupons = $this->getJson('/api/v1/coupons')->assertOk()->json('data');
        foreach ($coupons as $c) {
            $this->assertTrue($c['isActive']);
        }
    }

    public function test_leads(): void
    {
        $this->postJson('/api/v1/leads/contact', [
            'name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '+91 9876543210', 'orderNumber' => '',
            'category' => 'shipping', 'subject' => 'Order delivery update', 'message' => 'Hi, I wanted to check on the delivery status of my order.',
        ])->assertStatus(201)->assertJsonPath('data.type', 'contact')->assertJsonPath('data.status', 'new')->assertJsonPath('data.notes', '');

        $this->postJson('/api/v1/leads/contact', ['name' => 'x', 'email' => 'bad', 'subject' => 's', 'message' => 'short'])
            ->assertStatus(422)->assertJsonStructure(['message', 'errors']);

        $this->postJson('/api/v1/leads/newsletter', ['email' => 'subscriber@example.com'])->assertStatus(201)->assertJsonPath('data.status', 'subscribed');
        $this->postJson('/api/v1/leads/newsletter', ['email' => 'subscriber@example.com'])->assertStatus(201);
        $this->assertSame(1, \App\Models\Lead::query()->where('type', 'newsletter')->where('email', 'subscriber@example.com')->count());
    }
}
