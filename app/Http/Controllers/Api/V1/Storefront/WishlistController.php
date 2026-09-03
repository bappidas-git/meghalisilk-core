<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\StoreWishlistItemRequest;
use App\Http\Resources\WishlistItemResource;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer wishlist (guide §13.11, §26).
 */
class WishlistController extends ApiController
{
    private const RELATIONS = ['product.images', 'product.variants', 'product.related', 'product.frequentlyBoughtTogether'];

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->wishlistItems()->with(self::RELATIONS)->orderBy('id')->get()
            ->filter(fn (WishlistItem $item) => $item->product !== null)->values();

        return $this->respond(WishlistItemResource::collection($items));
    }

    public function store(StoreWishlistItemRequest $request): JsonResponse
    {
        $product = Product::query()->find($request->validated('productId'));
        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages(['productId' => ['This product is no longer available.']]);
        }

        $item = WishlistItem::query()->firstOrNew(['user_id' => $request->user()->id, 'product_id' => $product->id]);
        $created = ! $item->exists;
        if ($created) {
            $item->created_at = now();
            $item->save();
        }

        return $this->respond(WishlistItemResource::make($item->load(self::RELATIONS)), $created ? 201 : 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->wishlistItems()->findOrFail($id)->delete();

        return $this->respond(null);
    }
}
