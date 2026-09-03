<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Storefront product shape (guide §15.3). costPrice is admin-only — see AdminProductResource.
 */
class ProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku ?? '',
            'shortDescription' => $this->short_description ?? '',
            'description' => $this->description ?? '',
            'categoryId' => $this->category_id,
            'brand' => $this->brand,
            'images' => $this->images->pluck('url')->values()->all(),
            'price' => (int) $this->price,
            'comparePrice' => (int) $this->compare_price,
            'stock' => (int) $this->stock,
            'lowStockThreshold' => (int) $this->low_stock_threshold,
            'weight' => (float) $this->weight,
            'dimensions' => $this->resource->dimensions(),
            'variants' => $this->variants->map(function ($variant) {
                $row = [
                    'id' => $variant->variant_key,
                    'name' => $variant->name,
                    'price' => (int) $variant->price,
                    'stock' => (int) $variant->stock,
                    'sku' => $variant->sku ?? '',
                ];
                if ($variant->attributes !== null) {
                    $row['attributes'] = $this->object($variant->attributes);
                }
                if ($variant->swatch_hex !== null) {
                    $row['swatchHex'] = $variant->swatch_hex;
                }

                return $row;
            })->values()->all(),
            'tags' => array_values($this->tags ?? []),
            'featured' => (bool) $this->featured,
            'trending' => (bool) $this->trending,
            'hot' => (bool) $this->hot,
            'isActive' => (bool) $this->is_active,
            'rating' => (float) $this->rating,
            'totalReviews' => (int) $this->total_reviews,
            'metaTitle' => $this->meta_title,
            'metaDescription' => $this->meta_description,
            'frequentlyBoughtTogetherIds' => $this->frequentlyBoughtTogether->pluck('id')->values()->all(),
            'relatedProductIds' => $this->related->pluck('id')->values()->all(),
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
