<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\AdminCancelOrderRequest;
use App\Http\Requests\Admin\UpdateOrderRequest;
use App\Http\Resources\AdminOrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AuditTrail;
use App\Services\NumberGenerator;
use App\Services\OrderCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin orders (guide §13.18, §24.7).
 */
class OrderController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->with(Order::DEFAULT_RELATIONS)
            ->when($request->filled('userId'), fn ($q) => $q->where('user_id', (int) $request->query('userId')))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get();

        return $this->respond(AdminOrderResource::collection($orders));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(AdminOrderResource::make(Order::query()->with(Order::DEFAULT_RELATIONS)->findOrFail($id)));
    }

    /** Partial update + optional timeline event. "Mark as Paid" also captures the linked payment. */
    public function update(UpdateOrderRequest $request, AuditTrail $audit, NumberGenerator $numbers, int $id): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $audit, $numbers, $id) {
            $order = Order::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            $map = [
                'fulfillmentStatus' => 'fulfillment_status', 'shippingStatus' => 'shipping_status',
                'paymentStatus' => 'payment_status', 'trackingNumber' => 'tracking_number', 'trackingUrl' => 'tracking_url',
                'notes' => 'notes', 'deliveredAt' => 'delivered_at', 'shippingAddress' => 'shipping_address',
            ];
            $attributes = [];
            foreach ($map as $key => $column) {
                if (array_key_exists($key, $data) && ! (in_array($key, ['fulfillmentStatus', 'shippingStatus', 'paymentStatus'], true) && $data[$key] === null)) {
                    $attributes[$column] = $data[$key];
                }
            }
            if (($attributes['shipping_status'] ?? null) === 'delivered' && empty($attributes['delivered_at']) && ! $order->delivered_at) {
                $attributes['delivered_at'] = now();
            }

            $markingPaid = ($attributes['payment_status'] ?? null) === 'paid' && $order->payment_status !== 'paid';

            $order->forceFill($attributes)->save();

            if ($markingPaid) {
                $payment = Payment::query()->where('order_id', $order->id)->orderByDesc('id')->lockForUpdate()->first();
                if ($payment && in_array($payment->status, ['pending', 'voided', 'failed'], true)) {
                    $payment->forceFill([
                        'status' => 'captured',
                        'transaction_id' => $payment->transaction_id ?: $numbers->manualTransactionId(),
                    ])->save();
                }
            }

            if (! empty($data['event']['action'])) {
                $audit->order($order, $data['event']['action'], $data['event']['note'] ?? null, $this->actor());
            }

            return $order;
        });

        return $this->respond(AdminOrderResource::make($order->fresh(Order::DEFAULT_RELATIONS)));
    }

    public function cancel(AdminCancelOrderRequest $request, OrderCancellationService $cancellation, int $id): JsonResponse
    {
        $order = Order::query()->findOrFail($id);
        $order = $cancellation->cancelByAdmin($order, $request->validated(), $this->actor());

        return $this->respond(AdminOrderResource::make($order));
    }
}
