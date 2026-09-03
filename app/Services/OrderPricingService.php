<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative money recomputation for POST /orders (guide §24.2).
 *
 * Each line: ['product' => Product, 'variant' => ?ProductVariant, 'quantity' => int].
 */
class OrderPricingService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly WalletService $wallet,
    ) {}

    public function subtotal(array $lines): int
    {
        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += $this->unitPrice($line) * (int) $line['quantity'];
        }

        return $subtotal;
    }

    public function unitPrice(array $line): int
    {
        return (int) ($line['variant']?->price ?? $line['product']->price);
    }

    /**
     * @return array{subtotal:int,discount:int,shipping:int,shippingMethodId:?int,tax:int,total:int,storeCredit:int,amountPayable:int,codFee:int,codAvailable:bool,paymentMethod:string}
     */
    public function price(
        array $lines,
        ?Coupon $coupon,
        ?int $clientShippingAmount,
        string $paymentMethod,
        int $requestedStoreCredit,
        ?User $user,
    ): array {
        $store = $this->settings->get('store');
        $payment = $this->settings->get('payment');

        $subtotal = $this->subtotal($lines);
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;

        [$shipping, $shippingMethodId] = $this->resolveShipping($subtotal, $clientShippingAmount);

        $taxRate = (float) ($store['taxRate'] ?? 0);
        $taxIncluded = (bool) ($store['taxIncluded'] ?? false);
        $taxableBase = max(0, $subtotal - $discount);
        $tax = $taxIncluded
            ? (int) round($taxableBase - $taxableBase / (1 + $taxRate / 100))
            : (int) round($taxableBase * $taxRate / 100);

        $total = $subtotal - $discount + $shipping + ($taxIncluded ? 0 : $tax);

        $walletBalance = $user ? $this->wallet->balance($user) : 0;
        $storeCredit = min(max(0, (int) round($requestedStoreCredit)), $walletBalance, $total);
        $amountPayable = max(0, $total - $storeCredit);

        $codEnabled = (bool) ($payment['codEnabled'] ?? false);
        $codMin = (int) ($payment['codMinOrder'] ?? 0);
        $codMax = (int) ($payment['codMaxOrder'] ?? 0);
        $codAvailable = $codEnabled && $amountPayable > 0 && $amountPayable >= $codMin && ($codMax <= 0 || $amountPayable <= $codMax);
        $codFee = ($paymentMethod === 'cod' && $codAvailable) ? (int) ($payment['codFee'] ?? 0) : 0;

        if ($storeCredit > 0 && $amountPayable === 0) {
            $paymentMethod = 'store_credit';
        }

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'shippingMethodId' => $shippingMethodId,
            'tax' => $tax,
            'total' => $total + $codFee,
            'storeCredit' => $storeCredit,
            'amountPayable' => $amountPayable + $codFee,
            'codFee' => $codFee,
            'codAvailable' => $codAvailable,
            'paymentMethod' => $paymentMethod,
        ];
    }

    /**
     * The frontend does not send the chosen shipping method (guide §42.2): accept the client's
     * shippingAmount only when it equals the cost of an active method for this subtotal.
     *
     * @return array{0:int,1:?int}
     */
    private function resolveShipping(int $subtotal, ?int $clientShippingAmount): array
    {
        $methods = ShippingMethod::query()->where('is_active', true)->orderBy('id')->get();

        if ($methods->isEmpty()) {
            if ($clientShippingAmount !== null && $clientShippingAmount !== 0) {
                $this->rejectShipping();
            }

            return [0, null];
        }

        if ($clientShippingAmount === null) {
            $first = $methods->first();

            return [$first->costFor($subtotal), $first->id];
        }

        $matches = $methods->filter(fn (ShippingMethod $m) => $m->costFor($subtotal) === $clientShippingAmount);

        if ($matches->isEmpty()) {
            $this->rejectShipping();
        }

        return [$clientShippingAmount, $matches->count() === 1 ? $matches->first()->id : null];
    }

    private function rejectShipping(): never
    {
        throw ValidationException::withMessages([
            'shippingAmount' => ['The shipping amount does not match any available shipping method.'],
        ]);
    }
}
