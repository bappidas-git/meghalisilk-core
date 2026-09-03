<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\StoreReviewRequest;
use App\Http\Requests\Admin\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ProductRatingService;
use Illuminate\Http\JsonResponse;

/**
 * Admin reviews (guide §13.23).
 */
class ReviewController extends ApiController
{
    public function index(): JsonResponse
    {
        $reviews = Review::query()->with('order')->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(ReviewResource::collection($reviews));
    }

    public function store(StoreReviewRequest $request, ProductRatingService $ratings): JsonResponse
    {
        $data = $request->validated();

        $review = Review::create([
            'product_id' => $data['productId'],
            'user_id' => null,
            'order_id' => null,
            'user_name' => $data['userName'],
            'rating' => $data['rating'],
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'status' => $data['status'] ?? 'approved',
            'is_verified_purchase' => (bool) ($data['isVerifiedPurchase'] ?? false),
            'helpful_count' => 0,
            'source' => 'admin',
        ]);

        $ratings->recompute($review->product_id);

        return $this->respond(ReviewResource::make($review->load('order')), 201);
    }

    public function update(UpdateReviewRequest $request, ProductRatingService $ratings, int $id): JsonResponse
    {
        $review = Review::query()->findOrFail($id);
        $data = $request->validated();

        $map = ['status' => 'status', 'rating' => 'rating', 'title' => 'title', 'body' => 'body', 'userName' => 'user_name', 'isVerifiedPurchase' => 'is_verified_purchase'];
        $attributes = [];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $data)) {
                $attributes[$column] = $data[$key];
            }
        }
        $review->fill($attributes)->save();
        $ratings->recompute($review->product_id);

        return $this->respond(ReviewResource::make($review->fresh('order')));
    }

    public function destroy(ProductRatingService $ratings, int $id): JsonResponse
    {
        $review = Review::query()->findOrFail($id);
        $productId = $review->product_id;
        $review->delete();
        $ratings->recompute($productId);

        return $this->respond(null);
    }
}
