<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Two-step order refunds and direct payment refunds (guide §24.5).
 */
class RefundService
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly AuditTrail $audit,
        private readonly WalletService $wallet,
    ) {}

    /** a. Initiate — order → processing, payment → refund_pending, ledger row pending. */
    public function initiate(Order $order, int $amount, string $method, string $reason, ?string $reference, string $actor, string $type = 'order_refund'): Order
    {
        return DB::transaction(function () use ($order, $amount, $method, $reason, $reference, $actor, $type) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->refund_status === 'processing') {
                throw new ConflictHttpException('A refund is already in progress for this order.');
            }

            $payment = $this->paymentFor($order);
            $remaining = $payment ? $payment->remaining() : max(0, $order->amount_payable - $order->refunded_amount);

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['amount' => ["Refund can't exceed ".Money::inr($remaining)]]);
            }

            $this->openRefund($order, $payment, $amount, $method, $reason, $reference, $actor, $type);

            return $order->fresh(Order::DEFAULT_RELATIONS);
        });
    }

    /**
     * Shared by initiate() and the cancellation cascade (§24.4 step 3).
     */
    public function openRefund(Order $order, ?Payment $payment, int $amount, string $method, string $reason, ?string $reference, string $actor, string $type): Refund
    {
        $now = now();

        $order->forceFill([
            'refund_status' => 'processing',
            'refund_method' => $method,
            'pending_refund' => [
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'reference' => $reference,
                'initiatedAt' => $now->format('Y-m-d\TH:i:s.v\Z'),
                'by' => $actor,
            ],
        ])->save();

        $note = Money::inr($amount).' via '.Money::methodLabel($method)
            .($reference ? ' · ref '.$reference : '')
            .' — settlement pending';
        $this->audit->order($order, 'Refund initiated', $note, $actor);

        if ($payment && in_array($payment->status, ['captured', 'partially_refunded'], true)) {
            $payment->forceFill([
                'status' => 'refund_pending',
                'pending_refund' => [
                    'amount' => $amount,
                    'method' => $method,
                    'reason' => $reason,
                    'initiatedAt' => $now->format('Y-m-d\TH:i:s.v\Z'),
                    'by' => $actor,
                ],
            ])->save();
        }

        return Refund::create([
            'refund_number' => $this->numbers->refundNumber(),
            'type' => $type,
            'order_id' => $order->id,
            'payment_id' => $payment?->id,
            'amount' => $amount,
            'method' => $method,
            'reason' => $reason,
            'reference' => $reference,
            'status' => 'pending',
            'initiated_at' => $now,
            'by' => $actor,
        ]);
    }

    /** b. Complete — settle the pending refund. */
    public function complete(Order $order, string $actor): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->refund_status !== 'processing') {
                throw new ConflictHttpException('No refund is in progress for this order.');
            }

            $pending = $order->pending_refund ?? [];
            $amount = (int) ($pending['amount'] ?? 0);
            $method = $pending['method'] ?? $order->refund_method ?? 'original_payment';
            $reason = $pending['reason'] ?? null;

            $payment = $this->paymentFor($order);
            $settle = $amount;

            if ($payment) {
                $remaining = $payment->remaining();
                $settle = min($amount ?: $remaining, $remaining);
                if ($settle > 0) {
                    $this->appendPaymentRefund($payment, $settle, $reason ?: 'Refund completed', $actor);
                }
                $payment->forceFill(['pending_refund' => null, 'refund_reason' => $reason ?: $payment->refund_reason])->save();
            }

            $order->forceFill([
                'refund_status' => 'completed',
                'payment_status' => $payment ? ($payment->status === 'refunded' ? 'refunded' : 'partially_refunded') : 'refunded',
                'refunded_amount' => $order->refunded_amount + $amount,
                'refund_completed_at' => now(),
                'pending_refund' => null,
            ])->save();

            $this->audit->order($order, 'Refund completed', Money::inr($amount).' via '.Money::methodLabel($method).' settled to customer', $actor);

            $ledger = Refund::query()->where('order_id', $order->id)->where('status', 'pending')->orderByDesc('id')->first();
            if ($ledger) {
                $ledger->forceFill(['status' => 'completed', 'settled_at' => now(), 'amount' => $amount])->save();
            }

            if ($method === 'store_credit' && $settle > 0 && $order->user) {
                $this->wallet->credit($order->user, $settle, 'Refund for order '.$order->order_number, $order->id, $ledger?->id);
            }

            return $order->fresh(Order::DEFAULT_RELATIONS);
        });
    }

    /** c. Fail — roll the payment back so the admin can re-initiate. */
    public function fail(Order $order, ?string $note, string $actor): Order
    {
        return DB::transaction(function () use ($order, $note, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->refund_status !== 'processing') {
                throw new ConflictHttpException('No refund is in progress for this order.');
            }

            $order->forceFill(['refund_status' => 'failed'])->save();
            $this->audit->order($order, 'Refund failed', $note ?: 'Settlement failed — re-initiate the refund', $actor);

            $payment = $this->paymentFor($order);
            if ($payment && $payment->status === 'refund_pending') {
                $payment->forceFill([
                    'status' => $payment->refund_amount > 0 ? 'partially_refunded' : 'captured',
                    'pending_refund' => null,
                ])->save();
            }

            Refund::query()->where('order_id', $order->id)->where('status', 'pending')->orderByDesc('id')->first()
                ?->forceFill(['status' => 'failed'])->save();

            return $order->fresh(Order::DEFAULT_RELATIONS);
        });
    }

    /** d. Direct payment refund (POST /admin/payments/{id}/refund). */
    public function refundPayment(Payment $payment, int $amount, ?string $reason, string $actor): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $actor) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($amount > $payment->remaining()) {
                throw ValidationException::withMessages(['amount' => ['Refund exceeds the remaining '.Money::inr($payment->remaining())]]);
            }

            $this->appendPaymentRefund($payment, $amount, $reason, $actor);
            $payment->forceFill(['pending_refund' => null])->save();

            $order = $payment->order;
            if ($order) {
                $order->forceFill([
                    'payment_status' => $payment->status === 'refunded' ? 'refunded' : 'partially_refunded',
                    'refunded_amount' => $order->refunded_amount + $amount,
                ])->save();
                $this->audit->order($order, 'Refund issued ('.Money::inr($amount).')', $reason, $actor);
            }

            Refund::create([
                'refund_number' => $this->numbers->refundNumber(),
                'type' => 'payment_refund',
                'order_id' => $order?->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'method' => 'original_payment',
                'reason' => $reason,
                'status' => 'completed',
                'initiated_at' => now(),
                'settled_at' => now(),
                'by' => $actor,
            ]);

            return $payment->fresh(['refundEntries', 'order']);
        });
    }

    /**
     * Append a payments[].refunds[] entry and roll the running total / status (§24.5d mechanics).
     */
    public function appendPaymentRefund(Payment $payment, int $amount, ?string $reason, string $actor): void
    {
        $payment->refundEntries()->create([
            'ref_key' => $this->numbers->refKey(),
            'amount' => $amount,
            'reason' => $reason,
            'at' => now(),
            'by' => $actor,
        ]);

        $refunded = $payment->refund_amount + $amount;

        $payment->forceFill([
            'refund_amount' => $refunded,
            'status' => $refunded >= $payment->amount ? 'refunded' : 'partially_refunded',
            'refund_reason' => $reason ?: $payment->refund_reason,
        ])->save();
    }

    public function paymentFor(Order $order): ?Payment
    {
        return Payment::query()->where('order_id', $order->id)->orderByDesc('id')->lockForUpdate()->first();
    }
}
