<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Review;

/**
 * products.rating / total_reviews are recomputed from approved reviews on every review event (guide §27).
 */
class ProductRatingService
{
    public function recompute(int $productId): void
    {
        $stats = Review::query()
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
            ->first();

        Product::withTrashed()->whereKey($productId)->update([
            'rating' => $stats && $stats->total ? round((float) $stats->avg_rating, 1) : 0,
            'total_reviews' => (int) ($stats->total ?? 0),
        ]);
    }
}
