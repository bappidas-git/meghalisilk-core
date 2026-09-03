<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\ShippingMethodRequest;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;

class ShippingMethodController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(ShippingMethodResource::collection(ShippingMethod::query()->orderBy('id')->get()));
    }

    public function store(ShippingMethodRequest $request): JsonResponse
    {
        return $this->respond(ShippingMethodResource::make(ShippingMethod::create($this->attributes($request->validated()))), 201);
    }

    public function update(ShippingMethodRequest $request, int $id): JsonResponse
    {
        $method = ShippingMethod::query()->findOrFail($id);
        $method->fill($this->attributes($request->validated()))->save();

        return $this->respond(ShippingMethodResource::make($method->fresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        ShippingMethod::query()->findOrFail($id)->delete();

        return $this->respond(null);
    }

    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'carrier' => $data['carrier'] ?? null,
            'description' => $data['description'] ?? null,
            'rate_type' => $data['rateType'],
            'flat_rate' => (int) ($data['flatRate'] ?? 0),
            'free_above' => $data['freeAbove'] ?? null,
            'estimated_days' => $data['estimatedDays'] ?? null,
            'is_active' => (bool) ($data['isActive'] ?? true),
        ];
    }
}
