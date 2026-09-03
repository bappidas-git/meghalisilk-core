<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\CustomerReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer returns (guide §13.9 — defined in the service layer, no storefront screen calls them yet).
 */
class ReturnController extends ApiController
{
    private const RELATIONS = ['items', 'statusHistory', 'order'];

    public function store(CustomerReturnRequest $request, ReturnService $returns): JsonResponse
    {
        $data = $request->validated();
        $order = $request->user()->orders()->findOrFail($data['orderId']);

        return $this->respond(ReturnResource::make($returns->create($order, $data, 'Customer')), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $rows = $request->user()->returns()->with(self::RELATIONS)->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(ReturnResource::collection($rows));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->respond(ReturnResource::make($request->user()->returns()->with(self::RELATIONS)->findOrFail($id)));
    }
}
