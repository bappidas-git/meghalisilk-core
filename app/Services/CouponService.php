<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

/**
 * Coupon validation / redemption / restore (guide §13.10, §28).
 */
class CouponService
{
    /**
     * Validate a code for an order amount. Throws a 422 with the exact storefront message.
     */
    public function validate(?string $code, int $orderAmount, ?User $user): Coupon
    {
        $coupon = Coupon::query()->code($code)->first();

        if (! $coupon || ! $coupon->is_active) {
            $this->reject('Invalid coupon code');
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            $this->reject('Coupon has expired');
        }
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            $this->reject('Coupon usage limit reached');
        }
        if ($coupon->per_user_limit && $user) {
            $used = $this->timesUsedBy($coupon, $user);
            if ($used >= $coupon->per_user_limit) {
                $this->reject($coupon->per_user_limit === 1
                    ? 'You have already used this coupon'
                    : "You have already used this coupon {$coupon->per_user_limit} times");
            }
        }
        if ($orderAmount < $coupon->min_order_amount) {
            $this->reject('Minimum order amount is '.Money::inr($coupon->min_order_amount));
        }

        return $coupon;
    }

    public function timesUsedBy(Coupon $coupon, User $user): int
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('coupon_code', $coupon->code)
            ->where('coupon_restored', false)
            ->count();
    }

    public function redeem(Coupon $coupon): void
    {
        Coupon::query()->whereKey($coupon->id)->increment('used_count');
    }

    /**
     * Free the redemption held by an order. Returns true when a history row should be written.
     */
    public function restore(Order $order): bool
    {
        if (! $order->coupon_code || $order->coupon_restored) {
            return false;
        }

        $coupon = $order->coupon_id
            ? Coupon::query()->find($order->coupon_id)
            : Coupon::query()->code($order->coupon_code)->first();

        $order->forceFill(['coupon_restored' => true])->save();

        if ($coupon && $coupon->used_count > 0) {
            $coupon->forceFill(['used_count' => $coupon->used_count - 1])->save();

            return true;
        }

        return false;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['code' => [$message]]);
    }
}
