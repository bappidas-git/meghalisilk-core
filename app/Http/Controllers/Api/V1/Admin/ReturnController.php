<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\CreateReturnRequest;
use App\Http\Requests\Admin\UpdateReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\Order;
use App\Models\ProductReturn;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;

/**
 * Admin returns (guide §13.19).
 */
class ReturnController extends ApiController
{
    private const RELATIONS = ['items', 'statusHistory', 'order'];

    public function index(): JsonResponse
    {
        $rows = ProductReturn::query()->with(self::RELATIONS)->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(ReturnResource::collection($rows));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(ReturnResource::make(ProductReturn::query()->with(self::RELATIONS)->findOrFail($id)));
    }

    public function store(CreateReturnRequest $request, ReturnService $returns): JsonResponse
    {
        $data = $request->validated();
        $order = Order::query()->findOrFail($data['orderId']);

        return $this->respond(ReturnResource::make($returns->create($order, $data, $this->actor())), 201);
    }

    public function update(UpdateReturnRequest $request, ReturnService $returns, int $id): JsonResponse
    {
        $return = ProductReturn::query()->findOrFail($id);

        return $this->respond(ReturnResource::make($returns->update($return, $request->validated(), $this->actor())));
    }
}
