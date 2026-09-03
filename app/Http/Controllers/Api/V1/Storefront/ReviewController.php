<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\SubmitReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ProductRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer reviews (guide §13.2 POST /products/{id}/reviews, §13.8, §27).
 */
class ReviewController extends ApiController
{
    public function mine(Request $request): JsonResponse
    {
        $reviews = $request->user()->reviews()->with('order')->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(ReviewResource::collection($reviews));
    }

    /** Purchase-gated create-or-update; every submission returns to "pending". */
    public function store(SubmitReviewRequest $request, ProductRatingService $ratings, int $productId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $product = Product::query()->findOrFail($productId);
        $data = $request->validated();

        $eligible = Order::query()
            ->where('user_id', $user->id)
            ->where('shipping_status', 'delivered')
            ->whereNotIn('fulfillment_status', ['cancelled', 'returned'])
            ->whereNotIn('payment_status', ['failed', 'refunded'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->orderByDesc('created_at')
            ->get();

        if ($eligible->isEmpty()) {
            return response()->json(['message' => 'You can review this product once it has been delivered.'], 403);
        }

        $order = $eligible->firstWhere('id', (int) ($data['orderId'] ?? 0)) ?? $eligible->first();

        $review = Review::query()->firstOrNew(['product_id' => $product->id, 'user_id' => $user->id]);
        $created = ! $review->exists;

        $review->fill([
            'order_id' => $order->id,
            'user_name' => $user->reviewDisplayName(),
            'rating' => $data['rating'],
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
            'status' => 'pending',
            'is_verified_purchase' => true,
        ])->save();

        $ratings->recompute($product->id);

        return $this->respond(ReviewResource::make($review->load('order')), $created ? 201 : 200);
    }
}
