<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Admin categories (guide §13.17).
 */
class CategoryController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(CategoryResource::collection(Category::query()->orderBy('sort_order')->orderBy('id')->get()));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        return $this->respond(CategoryResource::make(Category::create($this->attributes($request->validated()))), 201);
    }

    public function update(CategoryRequest $request, int $id): JsonResponse
    {
        $category = Category::query()->findOrFail($id);
        $data = $request->validated();

        $parentId = isset($data['parentId']) ? (int) $data['parentId'] : null;
        if ($category->wouldCreateCycle($parentId)) {
            throw ValidationException::withMessages(['parentId' => ['A category cannot be its own parent or the child of one of its subcategories.']]);
        }

        $category->fill($this->attributes($data))->save();

        return $this->respond(CategoryResource::make($category->fresh()));
    }

    /** Never cascade: refuse with 409 while subcategories or products still reference the category. */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::query()->findOrFail($id);

        $children = Category::query()->where('parent_id', $category->id)->count();
        $products = Product::query()->where('category_id', $category->id)->count();

        if ($children > 0 || $products > 0) {
            $parts = [];
            if ($children > 0) {
                $parts[] = $children.' '.Str::plural('subcategory', $children);
            }
            if ($products > 0) {
                $parts[] = $products.' '.Str::plural('product', $products);
            }
            throw new ConflictHttpException('Cannot delete this category — '.implode(' and ', $parts).' still reference it. Reassign or remove them first.');
        }

        $category->delete();

        return $this->respond(null);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'image' => $data['image'] ?? null,
            'parent_id' => $data['parentId'] ?? null,
            'is_active' => (bool) ($data['isActive'] ?? true),
            'sort_order' => (int) ($data['sortOrder'] ?? 0),
            'show_in_main_menu' => (bool) ($data['showInMainMenu'] ?? false),
            'menu_order' => (int) ($data['menuOrder'] ?? 0),
        ];
    }
}
