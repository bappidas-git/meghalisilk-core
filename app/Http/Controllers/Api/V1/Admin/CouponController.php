<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\CouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;

/**
 * Admin coupons (guide §13.22). usedCount is server-owned and never reset.
 */
class CouponController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(CouponResource::collection(Coupon::query()->orderBy('id')->get()));
    }

    public function store(CouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($this->attributes($request->validated()) + ['used_count' => 0]);

        return $this->respond(CouponResource::make($coupon), 201);
    }

    public function update(CouponRequest $request, int $id): JsonResponse
    {
        $coupon = Coupon::query()->findOrFail($id);
        $coupon->fill($this->attributes($request->validated()))->save();

        return $this->respond(CouponResource::make($coupon->fresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        Coupon::query()->findOrFail($id)->delete();

        return $this->respond(null);
    }

    private function attributes(array $data): array
    {
        return [
            'code' => Coupon::normalizeCode($data['code']),
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'value' => (int) $data['value'],
            'min_order_amount' => (int) ($data['minOrderAmount'] ?? 0),
            'max_discount' => $data['maxDiscount'] ?? null,
            'usage_limit' => $data['usageLimit'] ?? null,
            'per_user_limit' => $data['perUserLimit'] ?? null,
            'is_active' => (bool) ($data['isActive'] ?? true),
            'expires_at' => $data['expiresAt'] ?? null,
        ];
    }
}
