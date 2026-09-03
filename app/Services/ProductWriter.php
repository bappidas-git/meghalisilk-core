<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\WishlistItem;
use Illuminate\Support\Facades\DB;

/**
 * Admin product writes: scalar columns + images / variants / links (guide §13.16).
 */
class ProductWriter
{
    private const SCALARS = [
        'name' => 'name', 'slug' => 'slug', 'sku' => 'sku', 'shortDescription' => 'short_description',
        'description' => 'description', 'categoryId' => 'category_id', 'brand' => 'brand', 'price' => 'price',
        'comparePrice' => 'compare_price', 'costPrice' => 'cost_price', 'stock' => 'stock',
        'lowStockThreshold' => 'low_stock_threshold', 'weight' => 'weight', 'tags' => 'tags', 'featured' => 'featured',
        'trending' => 'trending', 'hot' => 'hot', 'isActive' => 'is_active', 'metaTitle' => 'meta_title',
        'metaDescription' => 'meta_description',
    ];

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create($this->attributes($data, null));
            $this->syncChildren($product, $data);

            return $product->fresh(Product::STOREFRONT_RELATIONS);
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->fill($this->attributes($data, $product))->save();
            $this->syncChildren($product, $data);

            return $product->fresh(Product::STOREFRONT_RELATIONS);
        });
    }

    /** Soft delete + remove the rows that must not keep pointing at a hidden product. */
    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            CartItem::query()->where('product_id', $product->id)->delete();
            WishlistItem::query()->where('product_id', $product->id)->delete();
            ProductLink::query()->where('product_id', $product->id)->orWhere('linked_product_id', $product->id)->delete();
            $product->faqs()->detach();
            $product->delete();
        });
    }

    private function attributes(array $data, ?Product $existing): array
    {
        $attributes = [];
        foreach (self::SCALARS as $key => $column) {
            if (array_key_exists($key, $data)) {
                $attributes[$column] = $data[$key];
            }
        }
        if (array_key_exists('sku', $attributes)) {
            $attributes['sku'] = $attributes['sku'] === '' ? null : $attributes['sku'];
        }
        if (array_key_exists('tags', $attributes)) {
            $attributes['tags'] = array_values(array_filter(array_map('strval', (array) ($attributes['tags'] ?? [])), fn ($t) => $t !== ''));
        }
        if (array_key_exists('dimensions', $data)) {
            $dims = $data['dimensions'];
            $attributes['dim_length'] = is_array($dims) ? ($dims['length'] ?? null) : null;
            $attributes['dim_width'] = is_array($dims) ? ($dims['width'] ?? null) : null;
            $attributes['dim_height'] = is_array($dims) ? ($dims['height'] ?? null) : null;
        }
        if (! $existing) {
            $attributes += ['price' => 0, 'stock' => 0, 'tags' => []];
        }

        return $attributes;
    }

    private function syncChildren(Product $product, array $data): void
    {
        if (array_key_exists('images', $data)) {
            $product->images()->delete();
            foreach (array_values((array) $data['images']) as $i => $url) {
                if (is_string($url) && trim($url) !== '') {
                    $product->images()->create(['url' => trim($url), 'sort_order' => $i]);
                }
            }
        }

        if (array_key_exists('variants', $data)) {
            $incoming = collect((array) $data['variants'])->values();
            $keys = $incoming->pluck('id')->map(fn ($k) => (string) $k)->all();
            $product->variants()->whereNotIn('variant_key', $keys ?: [''])->delete();

            foreach ($incoming as $i => $variant) {
                $product->variants()->updateOrCreate(
                    ['variant_key' => (string) $variant['id']],
                    [
                        'name' => $variant['name'],
                        'price' => (int) ($variant['price'] ?? 0),
                        'stock' => (int) ($variant['stock'] ?? 0),
                        'sku' => ($variant['sku'] ?? '') === '' ? null : $variant['sku'],
                        'attributes' => $variant['attributes'] ?? null,
                        'swatch_hex' => $variant['swatchHex'] ?? null,
                        'sort_order' => $i,
                    ]
                );
            }
        }

        foreach (['relatedProductIds' => 'related', 'frequentlyBoughtTogetherIds' => 'fbt'] as $key => $type) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $ids = collect((array) $data[$key])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0 && $id !== $product->id)->unique()->values();
            $existing = Product::withTrashed()->whereIn('id', $ids)->pluck('id')->all();
            ProductLink::query()->where('product_id', $product->id)->where('type', $type)->delete();
            $order = 0;
            foreach ($ids as $id) {
                if (in_array($id, $existing, true)) {
                    ProductLink::query()->insert([
                        'product_id' => $product->id, 'linked_product_id' => $id, 'type' => $type, 'sort_order' => $order++,
                    ]);
                }
            }
        }
    }
}
