<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Hydrated cart line (guide §15.7): product snapshot fields come from the live product.
 */
class CartItemResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $variant = $product?->variantByKey($this->variant_key);

        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'productId' => $this->product_id,
            'variantId' => $this->variant_key,
            'variantName' => $variant?->name,
            'name' => $product?->name,
            'image' => $product?->images->first()?->url,
            'price' => (int) ($variant?->price ?? $product?->price ?? 0),
            'comparePrice' => (int) ($product?->compare_price ?? 0),
            'currency' => 'INR',
            'quantity' => (int) $this->quantity,
            'stock' => (int) ($variant?->stock ?? $product?->stock ?? 0),
        ];
    }
}
