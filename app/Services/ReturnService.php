<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\Refund;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Returns: creation (§13.19 / §13.9) and the refund cascade (§24.6).
 */
class ReturnService
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly AuditTrail $audit,
        private readonly RefundService $refunds,
        private readonly CouponService $coupons,
        private readonly InventoryService $inventory,
        private readonly WalletService $wallet,
    ) {}

    public function create(Order $order, array $data, string $actor): ProductReturn
    {
        return DB::transaction(function () use ($order, $data, $actor) {
            $order->loadMissing('items');

            if ($order->fulfillment_status === 'cancelled') {
                throw ValidationException::withMessages(['orderId' => ['Cannot create a return for a cancelled order.']]);
            }

            $rows = [];
            $gross = 0;
            foreach ($data['items'] as $index => $item) {
                $variantKey = $item['variantId'] ?? null;
                $orderItem = $order->items->first(function ($oi) use ($item, $variantKey) {
                    return (int) $oi->product_id === (int) $item['productId']
                        && (string) ($oi->variant_key ?? '') === (string) ($variantKey ?? '');
                });
                if (! $orderItem) {
                    throw ValidationException::withMessages(["items.$index.productId" => ['This item is not part of the order.']]);
                }
                $qty = (int) $item['quantity'];
                if ($qty < 1 || $qty > $orderItem->quantity) {
                    throw ValidationException::withMessages(["items.$index.quantity" => ["Quantity cannot exceed the {$orderItem->quantity} ordered."]]);
                }
                $rows[] = [
                    'product_id' => $orderItem->product_id,
                    'variant_key' => $orderItem->variant_key,
                    'variant_name' => $orderItem->variant_name,
                    'name' => $orderItem->name,
                    'sku' => $orderItem->sku,
                    'price' => $orderItem->price,
                    'quantity' => $qty,
                    'subtotal' => $orderItem->price * $qty,
                ];
                $gross += $orderItem->price * $qty;
            }

            $couponShare = $order->subtotal > 0
                ? min($gross, (int) round($gross / $order->subtotal * $order->discount_amount))
                : 0;

            $return = ProductReturn::create([
                'return_number' => $this->numbers->returnNumber(),
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'reason' => $data['reason'],
                'reason_details' => $data['reasonDetails'] ?? null,
                'status' => 'requested',
                'refund_amount' => max(0, $gross - $couponShare),
                'refund_status' => 'pending',
                'refund_method' => $data['refundMethod'] ?? 'original_payment',
                'deduction_amount' => 0,
                'restocked' => false,
                'images' => [],
                'notes' => '',
            ]);

            $return->items()->createMany($rows);
            $this->audit->return($return, 'Return created', 'Reason: '.$data['reason'], $actor);

            return $return->fresh(['items', 'statusHistory', 'order']);
        });
    }

    public function update(ProductReturn $return, array $data, string $actor): ProductReturn
    {
        return DB::transaction(function () use ($return, $data, $actor) {
            $return = ProductReturn::query()->with('items')->whereKey($return->id)->lockForUpdate()->firstOrFail();

            $wasSettled = $return->status === 'refunded' || $return->refund_status === 'processed';

            if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== $return->status) {
                if (! $return->canTransitionTo($data['status'])) {
                    throw new ConflictHttpException("A {$return->status} return cannot be marked {$data['status']}.");
                }
            }

            $map = [
                'status' => 'status', 'notes' => 'notes', 'rejectReason' => 'reject_reason', 'refundStatus' => 'refund_status',
                'deductionAmount' => 'deduction_amount', 'refundMethod' => 'refund_method',
                'returnTrackingNumber' => 'return_tracking_number', 'returnTrackingUrl' => 'return_tracking_url',
                'returnCarrier' => 'return_carrier', 'pickupScheduledAt' => 'pickup_scheduled_at',
            ];
            $attributes = [];
            foreach ($map as $key => $column) {
                if (array_key_exists($key, $data)) {
                    $attributes[$column] = $data[$key];
                }
            }
            if (isset($attributes['deduction_amount']) && $attributes['deduction_amount'] > $return->refund_amount) {
                throw ValidationException::withMessages(['deductionAmount' => ['Deduction cannot exceed the refund amount.']]);
            }
            $return->forceFill($attributes)->save();

            if (! empty($data['event']['action'])) {
                $this->audit->return($return, $data['event']['action'], $data['event']['note'] ?? null, $actor);
            }

            $nowSettled = $return->status === 'refunded' || $return->refund_status === 'processed';
            if ($nowSettled && ! $wasSettled) {
                $this->reflectReturnRefund($return, $actor);
            }

            if (! empty($data['restock']) && ! $return->restocked) {
                $this->inventory->restock($return->items);
                $return->forceFill(['restocked' => true])->save();
            }

            return $return->fresh(['items', 'statusHistory', 'order']);
        });
    }

    /** §24.6 reflectReturnRefund */
    private function reflectReturnRefund(ProductReturn $return, string $actor): void
    {
        $order = Order::query()->with('items')->whereKey($return->order_id)->lockForUpdate()->first();
        $payable = max(0, $return->refund_amount - $return->deduction_amount);
        $payment = $order ? $this->refunds->paymentFor($order) : null;

        if ($payment && $payable > 0) {
            $settle = min($payable, $payment->remaining());
            if ($settle > 0) {
                $this->refunds->appendPaymentRefund($payment, $settle, 'Return '.$return->return_number, $actor);
            }
        }

        $couponRestored = false;
        if ($order && $order->coupon_code && ! $order->coupon_restored) {
            $returnedQty = (int) $return->items->sum('quantity');
            $orderedQty = (int) $order->items->sum('quantity');
            if ($returnedQty >= $orderedQty) {
                $code = $order->coupon_code;
                $couponRestored = $this->coupons->restore($order);
                if ($couponRestored) {
                    $this->audit->order($order, 'Coupon usage restored', $code.' freed for reuse', $actor);
                }
            }
        }

        if ($order) {
            $order->forceFill([
                'payment_status' => $payment ? ($payment->status === 'refunded' ? 'refunded' : 'partially_refunded') : 'refunded',
                'fulfillment_status' => 'returned',
                'refunded_amount' => $order->refunded_amount + $payable,
            ])->save();
            $this->audit->order($order, 'Return refund processed ('.$return->return_number.')', Money::inr($payable).' refunded', $actor);
        }

        $ledger = Refund::create([
            'refund_number' => $this->numbers->refundNumber(),
            'type' => 'return_refund',
            'order_id' => $order?->id,
            'return_id' => $return->id,
            'payment_id' => $payment?->id,
            'amount' => $payable,
            'method' => $return->refund_method,
            'reason' => 'Return '.$return->return_number,
            'status' => 'completed',
            'coupon_restored' => $couponRestored,
            'initiated_at' => now(),
            'settled_at' => now(),
            'by' => $actor,
        ]);

        if ($return->refund_method === 'store_credit' && $payable > 0 && ! $return->store_credit_credited && $return->user_id) {
            $user = User::withTrashed()->find($return->user_id);
            if ($user) {
                $this->wallet->credit($user, $payable, 'Refund for return '.$return->return_number, $order?->id, $ledger->id);
                $return->forceFill(['store_credit_credited' => true])->save();
            }
        }
    }
}
