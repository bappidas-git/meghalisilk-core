<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        $categories = Category::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        return $this->respond(CategoryResource::collection($categories));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(CategoryResource::make(Category::query()->findOrFail($id)));
    }

    public function showBySlug(string $slug): JsonResponse
    {
        return $this->respond(CategoryResource::make(Category::query()->where('slug', $slug)->firstOrFail()));
    }
}
