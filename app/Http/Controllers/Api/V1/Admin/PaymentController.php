<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\PaymentRefundRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin payments (guide §13.20).
 */
class PaymentController extends ApiController
{
    private const RELATIONS = ['refundEntries', 'order'];

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::query()->with(self::RELATIONS)
            ->when($request->filled('orderId'), fn ($q) => $q->where('order_id', (int) $request->query('orderId')))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();

        return $this->respond(PaymentResource::collection($payments));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(PaymentResource::make(Payment::query()->with(self::RELATIONS)->findOrFail($id)));
    }

    public function refund(PaymentRefundRequest $request, RefundService $refunds, int $id): JsonResponse
    {
        $data = $request->validated();
        $payment = $refunds->refundPayment(Payment::query()->findOrFail($id), (int) round((float) $data['amount']), $data['reason'] ?? null, $this->actor());

        return $this->respond(PaymentResource::make($payment));
    }
}
