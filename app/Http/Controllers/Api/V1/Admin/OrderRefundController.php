<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\FailRefundRequest;
use App\Http\Requests\Admin\InitiateRefundRequest;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;

/**
 * Two-step order refund lifecycle (guide §13.18, §24.5).
 */
class OrderRefundController extends ApiController
{
    public function initiate(InitiateRefundRequest $request, RefundService $refunds, int $id): JsonResponse
    {
        $data = $request->validated();
        $order = $refunds->initiate(
            Order::query()->findOrFail($id),
            (int) round((float) $data['amount']),
            $data['method'],
            $data['reason'],
            $data['reference'] ?? null,
            $this->actor(),
        );

        return $this->respond(AdminOrderResource::make($order));
    }

    public function complete(RefundService $refunds, int $id): JsonResponse
    {
        return $this->respond(AdminOrderResource::make($refunds->complete(Order::query()->findOrFail($id), $this->actor())));
    }

    public function fail(FailRefundRequest $request, RefundService $refunds, int $id): JsonResponse
    {
        $order = $refunds->fail(Order::query()->findOrFail($id), $request->validated('note'), $this->actor());

        return $this->respond(AdminOrderResource::make($order));
    }
}
