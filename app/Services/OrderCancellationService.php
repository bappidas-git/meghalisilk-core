<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Shared cancellation cascade (guide §24.4) — customer and admin resolve to identical state.
 *
 * Options: reason, restock (bool), refund ({method, amount?, reference?}|null), voidPayment (bool),
 * recall ({trackingNumber, trackingUrl, carrier?}|null).
 */
class OrderCancellationService
{
    public function __construct(
        private readonly RefundService $refunds,
        private readonly CouponService $coupons,
        private readonly InventoryService $inventory,
        private readonly WalletService $wallet,
        private readonly AuditTrail $audit,
    ) {}

    /** Customer path: allowed only while the derived status is "processing". */
    public function cancelByCustomer(Order $order, ?string $reason): Order
    {
        if ($order->derivedStatus() !== 'processing') {
            throw new ConflictHttpException('This order can no longer be cancelled.');
        }

        $options = ['reason' => $reason ?: 'Cancelled by customer', 'restock' => true];

        if ($order->amount_payable > 0 && in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
            $options['refund'] = ['method' => $order->isOnlinePayment() ? 'original_payment' : 'bank_transfer'];
        } elseif ($order->amount_payable > 0) {
            $options['voidPayment'] = true;
        }

        return $this->cancel($order, $options, 'Customer');
    }

    /** Admin path: refuses delivered orders (use Returns) and already closed orders. */
    public function cancelByAdmin(Order $order, array $options, string $actor): Order
    {
        if ($order->shipping_status === 'delivered') {
            throw new ConflictHttpException('Delivered orders cannot be cancelled — create a return instead.');
        }

        return $this->cancel($order, $options, $actor);
    }

    public function cancel(Order $order, array $options, string $actor): Order
    {
        return DB::transaction(function () use ($order, $options, $actor) {
            $order = Order::query()->with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($order->fulfillment_status, ['cancelled', 'returned'], true)) {
                throw new ConflictHttpException('This order is already '.$order->fulfillment_status.'.');
            }

            $reason = $options['reason'] ?? 'Order cancelled';
            $now = now();

            // 1. Cancel
            $order->forceFill([
                'fulfillment_status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => $now,
            ])->save();
            $this->audit->order($order, 'Order cancelled', $reason, $actor);

            // 2. Recall the shipment
            $recall = $options['recall'] ?? null;
            if (is_array($recall)) {
                $order->forceFill([
                    'recall' => [
                        'trackingNumber' => $recall['trackingNumber'] ?? null,
                        'trackingUrl' => $recall['trackingUrl'] ?? null,
                        'carrier' => $recall['carrier'] ?? null,
                        'scheduledAt' => $now->format('Y-m-d\TH:i:s.v\Z'),
                        'by' => $actor,
                    ],
                    'shipping_status' => 'recalled',
                ])->save();
                $this->audit->order(
                    $order,
                    'Shipment recall initiated',
                    ! empty($recall['trackingNumber']) ? 'Return tracking '.$recall['trackingNumber'] : 'Parcel recalled to warehouse',
                    $actor
                );
            }

            // 3. Refund or void
            $payment = $this->refunds->paymentFor($order);
            $refund = $options['refund'] ?? null;
            if (is_array($refund) && ! empty($refund['method'])) {
                $amount = (int) ($refund['amount'] ?? 0);
                if ($amount <= 0) {
                    $amount = max(0, ($order->amount_payable ?? $order->total) - $order->refunded_amount);
                }
                if ($amount > 0) {
                    $this->refunds->openRefund(
                        $order,
                        $payment,
                        $amount,
                        $refund['method'],
                        $reason ?: 'Order cancelled',
                        $refund['reference'] ?? null,
                        $actor,
                        is_array($recall) ? 'recall_refund' : 'order_cancellation',
                    );
                }
            } elseif (! empty($options['voidPayment']) || $order->payment_status === 'pending') {
                $order->forceFill(['payment_status' => 'voided'])->save();
                $this->audit->order(
                    $order,
                    'Payment voided',
                    $order->payment_method === 'cod' ? 'Cash on delivery not collected' : 'No captured payment to refund',
                    $actor
                );
                if ($payment && $payment->status === 'pending') {
                    $payment->forceFill(['status' => 'voided', 'refund_reason' => $reason])->save();
                }
            }

            // 4. Return store credit
            if ($order->store_credit_used > 0 && ! $order->store_credit_returned && $order->user_id) {
                $user = User::withTrashed()->find($order->user_id);
                $debited = (int) WalletTransaction::query()
                    ->where('order_id', $order->id)->where('type', 'debit')->sum('amount');
                if ($user && $debited > 0) {
                    $this->wallet->credit($user, $debited, 'Store credit returned — '.$order->order_number.' cancelled', $order->id);
                    $order->forceFill(['store_credit_returned' => true])->save();
                    $this->audit->order($order, 'Store credit returned', Money::inr($debited).' added back to your wallet', $actor);

                    if ($payment && $payment->gateway === 'store_credit' && in_array($payment->status, ['captured', 'partially_refunded'], true)) {
                        $this->refunds->appendPaymentRefund($payment, min($debited, $payment->remaining()), 'Store credit returned', $actor);
                    }
                }
            }

            // 5. Coupon
            if ($order->coupon_code && ! $order->coupon_restored) {
                $code = $order->coupon_code;
                if ($this->coupons->restore($order)) {
                    $this->audit->order($order, 'Coupon usage restored', $code.' freed for reuse', $actor);
                }
            }

            // 6. Restock
            if (! empty($options['restock'])) {
                $this->inventory->restock($order->items);
            }

            return $order->fresh(Order::DEFAULT_RELATIONS);
        });
    }
}
