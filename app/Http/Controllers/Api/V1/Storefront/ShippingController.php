<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;

class ShippingController extends ApiController
{
    public function methods(): JsonResponse
    {
        $methods = ShippingMethod::query()->where('is_active', true)->orderBy('id')->get();

        return $this->respond(ShippingMethodResource::collection($methods));
    }
}
