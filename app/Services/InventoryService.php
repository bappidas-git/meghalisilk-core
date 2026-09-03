<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Stock decrement on order placement and restock on cancellation / return (guide §24.4 step 6).
 */
class InventoryService
{
    public function decrement(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        Product::withTrashed()->whereKey($product->id)->decrement('stock', $quantity);

        if ($variant) {
            ProductVariant::query()->whereKey($variant->id)->decrement('stock', $quantity);
        }
    }

    /**
     * @param  iterable<object>  $items  rows carrying product_id, variant_key, quantity
     */
    public function restock(iterable $items): void
    {
        foreach ($items as $item) {
            if (! $item->product_id || $item->quantity <= 0) {
                continue;
            }

            Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);

            if ($item->variant_key) {
                ProductVariant::query()
                    ->where('product_id', $item->product_id)
                    ->where('variant_key', $item->variant_key)
                    ->increment('stock', $item->quantity);
            }
        }
    }
}
