<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;

class AdminCrudTest extends ApiTestCase
{
    private function productPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Test Chador', 'slug' => 'test-chador', 'sku' => 'MEK-TST-001', 'shortDescription' => 'short', 'description' => 'long',
            'categoryId' => 1, 'brand' => "Meghali's Silk", 'images' => ['https://res.cloudinary.com/x/a.png', 'https://res.cloudinary.com/x/b.png'],
            'price' => 32500, 'comparePrice' => 38000, 'costPrice' => 22400, 'stock' => 9, 'lowStockThreshold' => 3, 'weight' => 1.15,
            'dimensions' => ['length' => 38, 'width' => 28, 'height' => 8],
            'variants' => [
                ['id' => 'v1', 'name' => 'Natural Gold', 'price' => 32500, 'stock' => 5, 'sku' => 'MEK-TST-001-NGD'],
                ['id' => 'v-1712345678901-123', 'name' => 'Rust', 'price' => 33500, 'stock' => 4, 'sku' => ''],
            ],
            'tags' => ['muga', 'test'], 'featured' => true, 'trending' => false, 'hot' => false, 'isActive' => true,
            'metaTitle' => 'meta', 'metaDescription' => 'meta desc',
        ], $overrides);
    }

    public function test_products_crud(): void
    {
        $admin = $this->adminHeaders();

        $this->assertCount(6, $this->getJson('/api/v1/admin/products', $admin)->assertOk()->json('data'));
        $this->assertArrayHasKey('costPrice', $this->getJson('/api/v1/admin/products/1', $admin)->assertOk()->json('data'));

        $created = $this->postJson('/api/v1/admin/products', $this->productPayload(), $admin)->assertStatus(201)->json('data');
        $this->assertSame('test-chador', $created['slug']);
        $this->assertSame(22400, $created['costPrice']);
        $this->assertSame(['v1', 'v-1712345678901-123'], array_column($created['variants'], 'id'));
        $this->assertSame('', $created['variants'][1]['sku']);
        $this->assertCount(2, $created['images']);
        $this->assertSame(['muga', 'test'], $created['tags']);

        $this->postJson('/api/v1/admin/products', $this->productPayload(), $admin)->assertStatus(422)->assertJsonPath('message', 'A product with this slug already exists.');
        $this->postJson('/api/v1/admin/products', $this->productPayload(['slug' => 'Bad Slug']), $admin)->assertStatus(422);
        $this->postJson('/api/v1/admin/products', $this->productPayload(['slug' => 'no-price', 'price' => 0, 'variants' => []]), $admin)->assertStatus(422);

        $update = $this->productPayload([
            'id' => 999, 'rating' => 4.8, 'totalReviews' => 41, 'createdAt' => '2020-01-01T00:00:00.000Z', 'updatedAt' => '2020-01-01T00:00:00.000Z',
            'stock' => 12, 'isActive' => false, 'dimensions' => null, 'images' => ['https://res.cloudinary.com/x/c.png'],
            'variants' => [['id' => 'v1', 'name' => 'Natural Gold (renamed)', 'price' => 32000, 'stock' => 2, 'sku' => 'X']],
            'relatedProductIds' => [1, 999], 'frequentlyBoughtTogetherIds' => [],
        ]);
        $updated = $this->putJson("/api/v1/admin/products/{$created['id']}", $update, $admin)->assertOk()->json('data');
        $this->assertSame($created['id'], $updated['id']);
        $this->assertFalse($updated['isActive']);
        $this->assertSame(12, $updated['stock']);
        $this->assertEquals(0, $updated['rating']);
        $this->assertNull($updated['dimensions']);
        $this->assertCount(1, $updated['variants']);
        $this->assertSame('Natural Gold (renamed)', $updated['variants'][0]['name']);
        $this->assertSame([1], $updated['relatedProductIds']);
        $this->assertNotSame('2020-01-01T00:00:00.000Z', $updated['createdAt']);

        // drafts are hidden from the storefront but listed for admins
        $this->getJson("/api/v1/products/{$created['id']}")->assertStatus(404);
        $this->assertCount(7, $this->getJson('/api/v1/admin/products', $admin)->json('data'));

        CartItem::create(['user_id' => 3, 'product_id' => $created['id'], 'variant_key' => 'v1', 'quantity' => 1]);
        $this->deleteJson("/api/v1/admin/products/{$created['id']}", [], $admin)->assertOk();
        $this->deleteJson("/api/v1/admin/products/{$created['id']}", [], $admin)->assertStatus(404);
        $this->assertNotNull(Product::withTrashed()->find($created['id'])->deleted_at);
        $this->assertSame(0, CartItem::query()->where('product_id', $created['id'])->count());
    }

    public function test_categories_crud_with_409_guard(): void
    {
        $admin = $this->adminHeaders();

        $created = $this->postJson('/api/v1/admin/categories', [
            'name' => 'Nuni Silk', 'slug' => 'nuni-silk', 'description' => 'Mulberry', 'image' => 'https://res.cloudinary.com/x/p.png',
            'parentId' => null, 'isActive' => true, 'sortOrder' => 4, 'showInMainMenu' => true, 'menuOrder' => 4,
        ], $admin)->assertStatus(201)->json('data');

        $this->putJson("/api/v1/admin/categories/{$created['id']}", ['id' => $created['id'], 'name' => 'Nuni Silk', 'slug' => 'nuni-silk', 'parentId' => 1, 'showInMainMenu' => false, 'menuOrder' => 0], $admin)
            ->assertOk()->assertJsonPath('data.parentId', 1)->assertJsonPath('data.showInMainMenu', false);

        // cycle guard: category 1 cannot become a child of its own child
        $this->putJson('/api/v1/admin/categories/1', ['name' => 'Muga Silk', 'slug' => 'muga-silk', 'parentId' => $created['id']], $admin)->assertStatus(422);

        $this->deleteJson('/api/v1/admin/categories/1', [], $admin)->assertStatus(409)
            ->assertJsonPath('message', 'Cannot delete this category — 1 subcategory and 2 products still reference it. Reassign or remove them first.');

        $this->deleteJson("/api/v1/admin/categories/{$created['id']}", [], $admin)->assertOk();
        $this->assertCount(3, $this->getJson('/api/v1/admin/categories', $admin)->json('data'));
    }

    public function test_coupons_crud(): void
    {
        $admin = $this->adminHeaders();
        $body = ['code' => 'bihu2026', 'description' => 'x', 'type' => 'fixed', 'value' => 750, 'minOrderAmount' => 6000, 'maxDiscount' => 750,
            'usageLimit' => 500, 'perUserLimit' => 1, 'isActive' => true, 'expiresAt' => '2027-04-30T23:59:59.000Z', 'usedCount' => 55];

        $created = $this->postJson('/api/v1/admin/coupons', $body, $admin)->assertStatus(201)->json('data');
        $this->assertSame('BIHU2026', $created['code']);
        $this->assertSame(0, $created['usedCount']);
        $this->assertSame('2027-04-30T23:59:59.000Z', $created['expiresAt']);

        $this->postJson('/api/v1/admin/coupons', $body, $admin)->assertStatus(422)->assertJsonPath('message', 'Coupon code "BIHU2026" already exists');
        $this->postJson('/api/v1/admin/coupons', ['code' => 'PCT', 'type' => 'percentage', 'value' => 120], $admin)->assertStatus(422);

        Coupon::query()->whereKey($created['id'])->update(['used_count' => 7]);
        $this->putJson("/api/v1/admin/coupons/{$created['id']}", ['code' => 'BIHU2026', 'type' => 'fixed', 'value' => 750, 'minOrderAmount' => 6000, 'usageLimit' => 800, 'perUserLimit' => 2, 'isActive' => true, 'expiresAt' => null], $admin)
            ->assertOk()->assertJsonPath('data.usedCount', 7)->assertJsonPath('data.perUserLimit', 2)->assertJsonPath('data.expiresAt', null);

        $this->deleteJson("/api/v1/admin/coupons/{$created['id']}", [], $admin)->assertOk();
        $this->assertCount(7, $this->getJson('/api/v1/admin/coupons', $admin)->json('data'));
    }

    public function test_shipping_methods_and_shiprocket(): void
    {
        $admin = $this->adminHeaders();
        $this->assertCount(5, $this->getJson('/api/v1/admin/shipping-methods', $admin)->assertOk()->json('data'));

        $created = $this->postJson('/api/v1/admin/shipping-methods', ['name' => 'Same-day Kolkata', 'carrier' => 'Dunzo', 'description' => 'x', 'rateType' => 'flat', 'flatRate' => 249, 'freeAbove' => null, 'estimatedDays' => '0', 'isActive' => true], $admin)
            ->assertStatus(201)->assertJsonPath('data.rateType', 'flat')->json('data');
        $this->putJson("/api/v1/admin/shipping-methods/{$created['id']}", ['name' => 'Same-day Kolkata', 'carrier' => 'Dunzo', 'rateType' => 'flat', 'flatRate' => 199, 'freeAbove' => 9999, 'estimatedDays' => '0', 'isActive' => false], $admin)
            ->assertOk()->assertJsonPath('data.isActive', false)->assertJsonPath('data.freeAbove', 9999);
        $this->deleteJson("/api/v1/admin/shipping-methods/{$created['id']}", [], $admin)->assertOk();
        $this->deleteJson("/api/v1/admin/shipping-methods/{$created['id']}", [], $admin)->assertStatus(404);

        $this->postJson('/api/v1/admin/shipping/shiprocket/order', ['orderId' => 1], $admin)->assertStatus(501)->assertJsonPath('message', 'Shiprocket integration is not enabled.');
        $this->getJson('/api/v1/admin/shipping/shiprocket/track/SHIP123456789IN', $admin)->assertStatus(501);
    }

    public function test_admin_reviews(): void
    {
        $admin = $this->adminHeaders();
        $this->assertNotEmpty($this->getJson('/api/v1/admin/reviews', $admin)->assertOk()->json('data'));

        $created = $this->postJson('/api/v1/admin/reviews', ['productId' => 1, 'userName' => 'Priya Menon', 'rating' => 5, 'title' => 't', 'body' => 'b', 'isVerifiedPurchase' => false, 'status' => 'approved'], $admin)
            ->assertStatus(201)->assertJsonPath('data.source', 'admin')->assertJsonPath('data.userId', null)->assertJsonPath('data.status', 'approved')->json('data');
        $this->assertContains($created['id'], array_column($this->getJson('/api/v1/products/1/reviews')->json('data'), 'id'));

        $this->patchJson("/api/v1/admin/reviews/{$created['id']}", ['status' => 'rejected'], $admin)->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertNotContains($created['id'], array_column($this->getJson('/api/v1/products/1/reviews')->json('data'), 'id'));
        $this->deleteJson("/api/v1/admin/reviews/{$created['id']}", [], $admin)->assertOk();
    }

    public function test_admin_users_and_leads(): void
    {
        $admin = $this->adminHeaders();
        $users = $this->getJson('/api/v1/admin/users', $admin)->assertOk()->json('data');
        $this->assertCount(4, $users);
        $this->assertArrayNotHasKey('password', $users[0]);
        $this->assertArrayHasKey('addresses', $users[0]);
        $this->getJson('/api/v1/admin/users/3', $admin)->assertOk()->assertJsonPath('data.storeCredit', 3918);

        $customer = $this->customerHeaders();
        $this->patchJson('/api/v1/admin/users/3', ['isActive' => false], $admin)->assertOk()->assertJsonPath('data.isActive', false);
        $this->getJson('/api/v1/auth/user', $customer)->assertStatus(401);
        $this->assertSame(0, User::query()->find(3)->tokens()->count());
        $this->patchJson('/api/v1/admin/users/3', ['isActive' => true], $admin)->assertOk()->assertJsonPath('data.isActive', true);
        $this->patchJson('/api/v1/admin/users/3', ['isActive' => 'maybe'], $admin)->assertStatus(422);

        $leads = $this->getJson('/api/v1/admin/leads', $admin)->assertOk()->json('data');
        $this->assertCount(6, $leads);
        $this->getJson('/api/v1/admin/leads/1', $admin)->assertOk()->assertJsonPath('data.type', 'contact');
        $this->patchJson('/api/v1/admin/leads/1', ['status' => 'contacted', 'notes' => 'Replied'], $admin)->assertOk()->assertJsonPath('data.status', 'contacted')->assertJsonPath('data.notes', 'Replied');
        $this->patchJson('/api/v1/admin/leads/1', ['status' => 'subscribed'], $admin)->assertStatus(422);
        $this->deleteJson('/api/v1/admin/leads/1', [], $admin)->assertOk();
        $this->getJson('/api/v1/admin/leads/1', $admin)->assertStatus(404);
    }

    public function test_settings_sections(): void
    {
        $admin = $this->adminHeaders();

        $all = $this->getJson('/api/v1/admin/settings', $admin)->assertOk()->json('data');
        $this->assertSame(['store', 'shipping', 'payment', 'notifications', 'seo', 'social'], array_keys($all));
        $this->assertArrayNotHasKey('shiprocketPassword', $all['shipping']);

        $this->patchJson('/api/v1/admin/settings/store', ['name' => "Meghali's Silk", 'taxRate' => 12, 'taxIncluded' => false, 'currency' => 'INR', 'currencySymbol' => '₹', 'email' => 'care@meghalisilk.com', 'phone' => '+91 33 4000 1100', 'address' => 'Kolkata', 'tagline' => 'x'], $admin)
            ->assertOk()->assertJsonPath('data.taxRate', 12)->assertJsonPath('data.timezone', 'Asia/Kolkata');
        $this->assertSame(12, $this->getJson('/api/v1/settings')->json('data.store.taxRate'));

        $this->patchJson('/api/v1/admin/settings/payment', ['codEnabled' => true, 'codFee' => 0, 'codMinOrder' => 0, 'codMaxOrder' => 50000], $admin)->assertOk()->assertJsonPath('data.codMaxOrder', 50000);
        $this->patchJson('/api/v1/admin/settings/social', ['facebook' => 'https://facebook.com/meghalisilk', 'twitter' => ''], $admin)
            ->assertOk()->assertJsonPath('data.twitter', '')->assertJsonPath('data.instagram', 'https://instagram.com/meghalisilk');
        $this->patchJson('/api/v1/admin/settings/social', ['facebook' => 'not-a-url'], $admin)->assertStatus(422);

        $this->patchJson('/api/v1/admin/settings/shipping', ['shiprocketEnabled' => true, 'shiprocketEmail' => 'ops@meghalisilk.com', 'shiprocketPassword' => 'secret-pass'], $admin)
            ->assertOk()->assertJsonPath('data.shiprocketEnabled', true)->assertJsonMissingPath('data.shiprocketPassword');
        $stored = Setting::query()->find('shipping')->data['shiprocketPassword'];
        $this->assertNotSame('secret-pass', $stored);
        $this->assertSame('secret-pass', Crypt::decryptString($stored));
        $this->assertStringNotContainsString('shiprocket', json_encode($this->getJson('/api/v1/settings')->json('data')));

        $this->patchJson('/api/v1/admin/settings/unknown', ['any' => 1], $admin)->assertStatus(404)->assertJsonStructure(['message']);
    }

    public function test_deals_and_hero_config(): void
    {
        $admin = $this->adminHeaders();
        $this->getJson('/api/v1/admin/deals/config', $admin)->assertOk()->assertJsonPath('data.enabled', true);

        $this->putJson('/api/v1/admin/deals/config', [
            'enabled' => true, 'hero' => ['tag' => 'Bihu Offers', 'title' => 'Honest Markdowns', 'subtitle' => 's'],
            'timer' => ['enabled' => true, 'endAt' => '2026-10-01T18:29:59.000Z', 'onExpiry' => 'endOfDay'],
            'featuredCouponIds' => [2, 3, 999], 'dealOfTheDayIds' => [1, 13, 34], 'featuredProductIds' => [1, 6, 13, 16, 35], 'updatedAt' => 'x',
        ], $admin)->assertOk()
            ->assertJsonPath('data.featuredCouponIds', [2, 3])
            ->assertJsonPath('data.dealOfTheDayIds', [1, 13])
            ->assertJsonPath('data.featuredProductIds', [1, 6, 13, 16]);
        $this->assertNotNull($this->getJson('/api/v1/deals/config')->json('data.updatedAt'));
        $this->putJson('/api/v1/admin/deals/config', ['enabled' => true, 'hero' => ['tag' => 'x']], $admin)->assertStatus(422);

        $hero = $this->getJson('/api/v1/admin/hero/config', $admin)->assertOk()->json('data');
        $hero['intervalMs'] = 5000;
        $this->putJson('/api/v1/admin/hero/config', $hero, $admin)->assertOk()->assertJsonPath('data.intervalMs', 5000)->assertJsonPath('data.openers.limit', 8);
        $this->assertSame(5000, $this->getJson('/api/v1/hero/config')->json('data.intervalMs'));
        $hero['intervalMs'] = 500;
        $this->putJson('/api/v1/admin/hero/config', $hero, $admin)->assertStatus(422);
        $hero['intervalMs'] = 5000;
        $hero['heights']['desktop']['max'] = 300;
        $this->putJson('/api/v1/admin/hero/config', $hero, $admin)->assertStatus(422);
    }

    public function test_banners_and_faqs_with_reorder(): void
    {
        $admin = $this->adminHeaders();

        $banners = $this->getJson('/api/v1/admin/banners', $admin)->assertOk()->json('data');
        $this->assertCount(6, $banners);

        $created = $this->postJson('/api/v1/admin/banners', [
            'title' => 'The Bridal Muga Edit', 'subtitle' => 's', 'eyebrow' => '', 'cta' => 'Shop', 'link' => '/products?category=muga-silk',
            'secondaryCtaLabel' => '', 'secondaryCtaLink' => '', 'backgroundType' => 'gradient', 'gradient' => 'linear-gradient(135deg,#1D1A16 0%,#8A6118 100%)',
            'image' => '', 'imagePosition' => 'right center', 'videoUrl' => '', 'videoPoster' => '', 'overlayOpacity' => null, 'textAlign' => 'left', 'durationMs' => 0, 'isActive' => true, 'sortOrder' => 6,
        ], $admin)->assertStatus(201)->assertJsonPath('data.eyebrow', '')->assertJsonPath('data.overlayOpacity', null)->json('data');

        $this->putJson("/api/v1/admin/banners/{$created['id']}", ['title' => 'x', 'link' => '/p', 'backgroundType' => 'image', 'image' => 'https://res.cloudinary.com/x/a.png', 'overlayOpacity' => 60, 'isActive' => false, 'durationMs' => 3000], $admin)
            ->assertOk()->assertJsonPath('data.backgroundType', 'image')->assertJsonPath('data.overlayOpacity', 60);
        $this->putJson("/api/v1/admin/banners/{$created['id']}", ['title' => 'x', 'link' => '/p', 'backgroundType' => 'image', 'durationMs' => 500], $admin)->assertStatus(422);

        $order = array_reverse(array_column($banners, 'id'));
        $reordered = $this->putJson('/api/v1/admin/banners/reorder', ['order' => $order], $admin)->assertOk()->json('data');
        $this->assertSame(array_merge($order, [$created['id']]), array_column($reordered, 'id'));
        $this->assertSame(range(0, 6), array_column($reordered, 'sortOrder'));
        $this->putJson('/api/v1/admin/banners/reorder', ['order' => [1, 2, 999]], $admin)->assertStatus(422);
        $this->putJson('/api/v1/admin/banners/reorder', ['order' => [1, 1]], $admin)->assertStatus(422);

        $this->deleteJson("/api/v1/admin/banners/{$created['id']}", [], $admin)->assertOk();
        $this->assertCount(6, $this->getJson('/api/v1/admin/banners', $admin)->json('data'));
        $this->assertCount(4, $this->getJson('/api/v1/banners')->json('data')); // only active slides on the storefront

        $faqs = $this->getJson('/api/v1/admin/faqs', $admin)->assertOk()->json('data');
        $this->assertCount(8, $faqs);
        $faq = $this->postJson('/api/v1/admin/faqs', ['question' => 'Handwoven?', 'answer' => 'Yes. Free above {freeShipping}.', 'placements' => ['product', 'help', 'home'], 'productIds' => [1, 13], 'isActive' => true, 'sortOrder' => 8, 'createdAt' => 'x'], $admin)
            ->assertStatus(201)->assertJsonPath('data.productIds', [1, 13])->assertJsonPath('data.placements', ['product', 'help', 'home'])->json('data');
        $this->postJson('/api/v1/admin/faqs', ['question' => 'q', 'answer' => 'a', 'placements' => ['nowhere']], $admin)->assertStatus(422);
        $this->postJson('/api/v1/admin/faqs', ['question' => 'q', 'answer' => 'a', 'placements' => ['help'], 'productIds' => [999]], $admin)->assertStatus(422);

        $this->putJson("/api/v1/admin/faqs/{$faq['id']}", ['question' => 'Handwoven?', 'answer' => 'Yes.', 'placements' => ['help'], 'productIds' => [], 'isActive' => false, 'sortOrder' => 8], $admin)
            ->assertOk()->assertJsonPath('data.isActive', false)->assertJsonPath('data.productIds', []);
        $this->assertNotContains($faq['id'], array_column($this->getJson('/api/v1/faqs')->json('data'), 'id'));

        $ids = array_column($faqs, 'id');
        $reordered = $this->putJson('/api/v1/admin/faqs/reorder', ['order' => array_reverse($ids)], $admin)->assertOk()->json('data');
        $this->assertSame(array_reverse($ids), array_slice(array_column($reordered, 'id'), 0, 8));
        $this->deleteJson("/api/v1/admin/faqs/{$faq['id']}", [], $admin)->assertOk();
    }
}
