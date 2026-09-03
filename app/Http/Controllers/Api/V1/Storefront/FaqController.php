<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;

class FaqController extends ApiController
{
    public function index(): JsonResponse
    {
        $faqs = Faq::query()->with('products')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return $this->respond(FaqResource::collection($faqs));
    }
}
