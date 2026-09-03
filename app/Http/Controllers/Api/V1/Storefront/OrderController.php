<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Storefront\CancelOrderRequest;
use App\Http\Requests\Storefront\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderCancellationService;
use App\Services\OrderPlacementService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Customer orders (guide §13.6). Every read is scoped to the token owner (404 otherwise).
 */
class OrderController extends ApiController
{
    public function store(PlaceOrderRequest $request, OrderPlacementService $placement): JsonResponse
    {
        $order = $placement->place($request->user(), $request->validated());

        return $this->respond(OrderResource::make($order), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $this->mine($request)->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(OrderResource::collection($orders));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->respond(OrderResource::make($this->mine($request)->findOrFail($id)));
    }

    /** Accepts the order number or, as a fallback, the numeric id (Checkout navigates with orderNumber || id). */
    public function showByNumber(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->mine($request)
            ->where(function ($q) use ($orderNumber) {
                $q->where('order_number', $orderNumber);
                if (ctype_digit($orderNumber)) {
                    $q->orWhere('id', (int) $orderNumber);
                }
            })
            ->firstOrFail();

        return $this->respond(OrderResource::make($order));
    }

    public function cancel(CancelOrderRequest $request, OrderCancellationService $cancellation, int $id): JsonResponse
    {
        $order = $this->mine($request)->findOrFail($id);
        $order = $cancellation->cancelByCustomer($order, $request->validated('reason'));

        return $this->respond(OrderResource::make($order));
    }

    private function mine(Request $request): HasMany
    {
        return $request->user()->orders()->with(Order::DEFAULT_RELATIONS);
    }
}
