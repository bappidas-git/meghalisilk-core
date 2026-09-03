<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends ApiController
{
    public function index(): JsonResponse
    {
        $banners = Banner::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return $this->respond(BannerResource::collection($banners));
    }
}
