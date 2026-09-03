<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\ValidateCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

/**
 * Public coupon list + validation (guide §13.10).
 */
class CouponController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(CouponResource::collection(Coupon::query()->activeUnexpired()->orderBy('id')->get()));
    }

    /** Public; when a customer token is present it wins over the body's userId (which is ignored). */
    public function validateCode(ValidateCouponRequest $request, CouponService $coupons): JsonResponse
    {
        $data = $request->validated();
        $user = auth('sanctum')->user();
        $user = $user instanceof User ? $user : null;

        $coupon = $coupons->validate($data['code'], (int) round((float) ($data['orderAmount'] ?? 0)), $user);

        return $this->respond(CouponResource::make($coupon));
    }
}
