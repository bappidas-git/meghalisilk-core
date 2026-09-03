<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * POST /orders — one transaction (guide §13.6, §22.2, §24.2).
 */
class OrderPlacementService
{
    public function __construct(
        private readonly OrderPricingService $pricing,
        private readonly CouponService $coupons,
        private readonly InventoryService $inventory,
        private readonly WalletService $wallet,
        private readonly NumberGenerator $numbers,
        private readonly AuditTrail $audit,
    ) {}

    public function place(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $lines = $this->resolveLines($data['items']);
            $subtotal = $this->pricing->subtotal($lines);

            $coupon = null;
            if (! empty($data['couponCode'])) {
                $coupon = $this->coupons->validate($data['couponCode'], $subtotal, $user);
                Coupon::query()->whereKey($coupon->id)->lockForUpdate()->first();
            }

            $requestedMethod = $data['paymentMethod'];
            $pricing = $this->pricing->price(
                $lines,
                $coupon,
                array_key_exists('shippingAmount', $data) && $data['shippingAmount'] !== null ? (int) $data['shippingAmount'] : null,
                $requestedMethod,
                (int) ($data['storeCreditUsed'] ?? 0),
                $user,
            );

            if ($requestedMethod === 'cod' && ! $pricing['codAvailable']) {
                throw ValidationException::withMessages(['paymentMethod' => ['Cash on delivery is not available for this order.']]);
            }

            if (isset($data['total']) && is_numeric($data['total']) && (int) $data['total'] !== $pricing['total']) {
                throw ValidationException::withMessages(['total' => ['Prices have changed, please review your cart.']]);
            }

            $paymentMethod = $pricing['paymentMethod'];
            $paymentStatus = $this->initialPaymentStatus($paymentMethod, $pricing);

            $order = Order::create([
                'order_number' => $this->numbers->orderNumber(),
                'user_id' => $user->id,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'subtotal' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount'],
                'shipping_amount' => $pricing['shipping'],
                'shipping_method_id' => $pricing['shippingMethodId'],
                'tax_amount' => $pricing['tax'],
                'cod_fee' => $pricing['codFee'],
                'total' => $pricing['total'],
                'store_credit_used' => $pricing['storeCredit'],
                'amount_payable' => $pricing['amountPayable'],
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'fulfillment_status' => 'unfulfilled',
                'shipping_status' => 'pending',
                'notes' => $data['notes'] ?? '',
                'shipping_address' => $this->addressSnapshot($data['shippingAddress']),
                'billing_address' => $this->addressSnapshot($data['billingAddress'] ?? $data['shippingAddress']),
            ]);

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $variant = $line['variant'];
                $unit = $this->pricing->unitPrice($line);
                $qty = (int) $line['quantity'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'variant_key' => $variant?->variant_key,
                    'variant_name' => $variant?->name,
                    'name' => $product->name.($variant ? ' - '.$variant->name : ''),
                    'image' => $product->images->first()?->url,
                    'sku' => $variant?->sku ?: $product->sku,
                    'price' => $unit,
                    'quantity' => $qty,
                    'subtotal' => $unit * $qty,
                ]);

                $this->inventory->decrement($product, $variant, $qty);
            }

            $this->audit->order($order, 'Order placed', $paymentMethod === 'cod' ? 'Cash on delivery' : null, 'Customer');

            $this->createPayment($order, $user, $paymentMethod, $paymentStatus);

            if ($coupon) {
                $this->coupons->redeem($coupon);
            }

            if ($pricing['storeCredit'] > 0) {
                $this->wallet->debit($user, $pricing['storeCredit'], 'Applied to order '.$order->order_number, $order->id);
            }

            $user->cartItems()->delete();

            return $order->fresh(Order::DEFAULT_RELATIONS);
        });
    }

    /**
     * @return array<int, array{product:Product,variant:?\App\Models\ProductVariant,quantity:int}>
     */
    private function resolveLines(array $items): array
    {
        $ids = collect($items)->pluck('productId')->map(fn ($id) => (int) $id)->unique()->all();

        $products = Product::query()
            ->with(['images', 'variants'])
            ->whereIn('id', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lines = [];
        foreach ($items as $index => $item) {
            $product = $products->get((int) $item['productId']);
            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(["items.$index.productId" => ['This product is no longer available.']]);
            }

            $variantKey = $item['variantId'] ?? null;
            $variant = null;
            if ($variantKey !== null && $variantKey !== '') {
                $variant = $product->variantByKey((string) $variantKey);
                if (! $variant) {
                    throw ValidationException::withMessages(["items.$index.variantId" => ["The selected option for {$product->name} is no longer available."]]);
                }
            }

            $qty = (int) $item['quantity'];
            $available = $variant ? $variant->stock : $product->stock;
            if ($qty > $available) {
                $label = $product->name.($variant ? ' - '.$variant->name : '');
                $message = $available <= 0 ? "{$label} is out of stock." : "Only {$available} left in stock for {$label}.";
                throw ValidationException::withMessages(["items.$index.quantity" => [$message]]);
            }

            $lines[] = ['product' => $product, 'variant' => $variant, 'quantity' => $qty];
        }

        return $lines;
    }

    private function initialPaymentStatus(string $paymentMethod, array $pricing): string
    {
        if ($paymentMethod === 'store_credit') {
            return 'paid';
        }
        if ($paymentMethod === 'cod') {
            return 'pending';
        }

        // Online methods without a gateway: guide §22.3 option A (pending) unless configured for parity.
        return config('store.trust_client_payment_status') ? 'paid' : 'pending';
    }

    private function createPayment(Order $order, User $user, string $paymentMethod, string $paymentStatus): Payment
    {
        $ref = strtoupper(base_convert((string) (int) (microtime(true) * 1000), 10, 36));

        if ($paymentMethod === 'store_credit') {
            $gateway = 'store_credit';
            $status = 'captured';
            $amount = $order->total;
            $transactionId = 'wallet_'.$ref;
            $gatewayOrderId = null;
        } elseif ($paymentMethod === 'cod') {
            $gateway = 'cod';
            $status = 'pending';
            $amount = $order->amount_payable;
            $transactionId = null;
            $gatewayOrderId = null;
        } else {
            $gateway = 'razorpay';
            $amount = $order->amount_payable;
            $status = $paymentStatus === 'paid' ? 'captured' : 'pending';
            $transactionId = $paymentStatus === 'paid' ? 'pay_MANUAL'.$ref : null;
            $gatewayOrderId = $paymentStatus === 'paid' ? 'order_MANUAL'.$ref : null;
        }

        return $order->payments()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'INR',
            'payment_method' => $paymentMethod,
            'gateway' => $gateway,
            'transaction_id' => $transactionId,
            'gateway_order_id' => $gatewayOrderId,
            'status' => $status,
            'gateway_response' => [],
            'store_credit_applied' => $order->store_credit_used,
        ]);
    }

    private function addressSnapshot(array $address): array
    {
        $keys = ['id', 'label', 'firstName', 'lastName', 'phone', 'addressLine1', 'addressLine2', 'city', 'state', 'postalCode', 'country', 'isDefault'];
        $snapshot = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $address)) {
                $snapshot[$key] = $address[$key];
            }
        }
        $snapshot['addressLine2'] = $snapshot['addressLine2'] ?? '';
        $snapshot['country'] = $snapshot['country'] ?: 'India';

        return $snapshot;
    }
}
