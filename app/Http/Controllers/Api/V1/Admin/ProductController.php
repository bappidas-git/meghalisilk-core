<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\AdminProductResource;
use App\Models\Product;
use App\Services\ProductWriter;
use Illuminate\Http\JsonResponse;

/**
 * Admin products (guide §13.16) — drafts included, costPrice included.
 */
class ProductController extends ApiController
{
    public function index(): JsonResponse
    {
        $products = Product::query()->with(Product::STOREFRONT_RELATIONS)->orderBy('id')->get();

        return $this->respond(AdminProductResource::collection($products));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(AdminProductResource::make(Product::query()->with(Product::STOREFRONT_RELATIONS)->findOrFail($id)));
    }

    public function store(ProductRequest $request, ProductWriter $writer): JsonResponse
    {
        return $this->respond(AdminProductResource::make($writer->create($request->validated())), 201);
    }

    /** Full replace of the editable fields; id / rating / totalReviews / timestamps are ignored. */
    public function update(ProductRequest $request, ProductWriter $writer, int $id): JsonResponse
    {
        $product = Product::query()->findOrFail($id);

        return $this->respond(AdminProductResource::make($writer->update($product, $request->validated())));
    }

    public function destroy(ProductWriter $writer, int $id): JsonResponse
    {
        $writer->delete(Product::query()->findOrFail($id));

        return $this->respond(null);
    }
}
