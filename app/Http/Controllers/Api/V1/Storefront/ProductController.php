<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ReviewResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public catalogue (guide §13.2). Only active products are visible; costPrice is never returned.
 */
class ProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $products = $this->visible()
            ->search($request->query('search'))
            ->orderBy('id')
            ->get();

        return $this->respond(ProductResource::collection($products));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(ProductResource::make($this->visible()->findOrFail($id)));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        return $this->respond(ProductResource::make($this->visible()->where('slug', $slug)->firstOrFail()));
    }

    public function featured(Request $request): JsonResponse
    {
        $products = $this->visible()->where('featured', true)
            ->orderByDesc('updated_at')->orderBy('id')
            ->limit($this->limit($request))->get();

        return $this->respond(ProductResource::collection($products));
    }

    public function trending(Request $request): JsonResponse
    {
        $products = $this->visible()->where('trending', true)
            ->orderByDesc('updated_at')->orderBy('id')
            ->limit($this->limit($request))->get();

        return $this->respond(ProductResource::collection($products));
    }

    /** Products in the category or any descendant (parent-includes-children rule). */
    public function byCategory(int $categoryId): JsonResponse
    {
        $category = Category::query()->findOrFail($categoryId);
        $products = $this->visible()
            ->whereIn('category_id', $category->descendantIdsIncludingSelf())
            ->orderBy('id')->get();

        return $this->respond(ProductResource::collection($products));
    }

    public function reviews(int $productId): JsonResponse
    {
        $product = Product::query()->findOrFail($productId);
        $reviews = $product->reviews()->with('order')
            ->where('status', 'approved')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();

        return $this->respond(ReviewResource::collection($reviews));
    }

    private function visible(): Builder
    {
        return Product::query()->active()->with(Product::STOREFRONT_RELATIONS);
    }

    private function limit(Request $request): int
    {
        return max(1, min(50, (int) $request->query('limit', 10)));
    }
}
