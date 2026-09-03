<?php

namespace Tests\Feature\Api;

use App\Models\Product;

class CartWishlistTest extends ApiTestCase
{
    public function test_cart_flow(): void
    {
        $headers = $this->customerHeaders();

        $add = $this->postJson('/api/v1/cart', [
            'productId' => '1', 'variantId' => 'v1', 'variantName' => 'Natural Gold', 'name' => 'x', 'image' => 'x',
            'price' => 1, 'comparePrice' => 1, 'currency' => 'INR', 'quantity' => 2, 'stock' => 5, 'userId' => '999',
        ], $headers);
        $add->assertStatus(201)
            ->assertJsonPath('data.productId', 1)
            ->assertJsonPath('data.userId', 3)
            ->assertJsonPath('data.variantId', 'v1')
            ->assertJsonPath('data.variantName', 'Natural Gold')
            ->assertJsonPath('data.price', 32500)
            ->assertJsonPath('data.stock', 5)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.currency', 'INR');
        $id = $add->json('data.id');

        // upsert on (product, variant) sets the quantity, clamped to stock
        $this->postJson('/api/v1/cart', ['productId' => 1, 'variantId' => 'v1', 'quantity' => 50], $headers)
            ->assertStatus(201)->assertJsonPath('data.id', $id)->assertJsonPath('data.quantity', 5);

        $this->patchJson("/api/v1/cart/$id", ['quantity' => 3], $headers)->assertOk()->assertJsonPath('data.quantity', 3);

        $lines = $this->getJson('/api/v1/cart', $headers)->assertOk()->json('data');
        $this->assertCount(1, $lines);
        $this->assertSame('Sualkuchi Muga Mekhela Chador — Natural Gold', $lines[0]['name']);

        // another customer cannot touch the line
        $this->deleteJson("/api/v1/cart/$id", [], $this->customerHeaders($this->customer(1)))->assertStatus(404);

        $this->deleteJson("/api/v1/cart/$id", [], $headers)->assertOk()->assertJsonPath('data', null);
        $this->deleteJson("/api/v1/cart/$id", [], $headers)->assertStatus(404);

        $this->postJson('/api/v1/cart', ['productId' => 6, 'variantId' => null, 'quantity' => 1], $headers)->assertStatus(201);
        $this->deleteJson('/api/v1/cart', [], $headers)->assertOk();
        $this->assertSame([], $this->getJson('/api/v1/cart', $headers)->json('data'));

        Product::query()->whereKey(10)->update(['is_active' => false]);
        $this->postJson('/api/v1/cart', ['productId' => 10, 'quantity' => 1], $headers)->assertStatus(422);
        $this->postJson('/api/v1/cart', ['productId' => 1, 'variantId' => 'nope', 'quantity' => 1], $headers)->assertStatus(422);
    }

    public function test_wishlist_flow(): void
    {
        $headers = $this->customerHeaders();

        $add = $this->postJson('/api/v1/wishlist', ['productId' => '16', 'name' => 'ignored', 'userId' => 1], $headers);
        $add->assertStatus(201)->assertJsonPath('data.productId', 16)->assertJsonPath('data.product.slug', 'pat-silk-saree-ivory-zari');
        $id = $add->json('data.id');

        $this->postJson('/api/v1/wishlist', ['productId' => 16], $headers)->assertOk()->assertJsonPath('data.id', $id);

        $rows = $this->getJson('/api/v1/wishlist', $headers)->assertOk()->json('data');
        $this->assertCount(4, $rows); // seed rows for products 1, 13, 19 + the new one
        $this->assertArrayHasKey('images', $rows[0]['product']);
        $this->assertArrayNotHasKey('costPrice', $rows[0]['product']);

        $this->deleteJson("/api/v1/wishlist/$id", [], $this->customerHeaders($this->customer(1)))->assertStatus(404);
        $this->deleteJson("/api/v1/wishlist/$id", [], $headers)->assertOk();
        $this->deleteJson("/api/v1/wishlist/$id", [], $headers)->assertStatus(404);
    }
}
