<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\StoreCartItemRequest;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Customer cart (guide §13.5, §25). Every endpoint is scoped to the token owner.
 */
class CartController extends ApiController
{
    private const RELATIONS = ['product.images', 'product.variants'];

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->cartItems()->with(self::RELATIONS)->orderBy('id')->get()
            ->filter(fn (CartItem $item) => $item->product !== null)->values();

        return $this->respond(CartItemResource::collection($items));
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = Product::query()->with('variants')->find($data['productId']);

        if (! $product || ! $product->is_active) {
            throw ValidationException::withMessages(['productId' => ['This product is no longer available.']]);
        }

        $variantKey = ($data['variantId'] ?? '') === '' ? null : $data['variantId'];
        $variant = null;
        if ($variantKey !== null) {
            $variant = $product->variantByKey($variantKey);
            if (! $variant) {
                throw ValidationException::withMessages(['variantId' => ['The selected option is not available.']]);
            }
        }

        $quantity = $this->clamp((int) $data['quantity'], $variant ? $variant->stock : $product->stock);

        $item = CartItem::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $product->id, 'variant_key' => $variantKey],
            ['quantity' => $quantity]
        );

        return $this->respond(CartItemResource::make($item->load(self::RELATIONS)), 201);
    }

    public function update(UpdateCartItemRequest $request, int $id): JsonResponse
    {
        $item = $request->user()->cartItems()->with(self::RELATIONS)->findOrFail($id);

        if ($request->has('quantity')) {
            $variant = $item->product?->variantByKey($item->variant_key);
            $stock = $variant ? $variant->stock : ($item->product?->stock ?? 0);
            $item->forceFill(['quantity' => $this->clamp((int) $request->validated('quantity'), $stock)])->save();
        }

        return $this->respond(CartItemResource::make($item->fresh(self::RELATIONS)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->cartItems()->findOrFail($id)->delete();

        return $this->respond(null);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->user()->cartItems()->delete();

        return $this->respond(null);
    }

    /** Clamp to stock (guide §13.5 decision); out-of-stock lines are rejected. */
    private function clamp(int $quantity, int $stock): int
    {
        if ($stock <= 0) {
            throw ValidationException::withMessages(['quantity' => ['This item is out of stock.']]);
        }

        return max(1, min($quantity, $stock));
    }
}
