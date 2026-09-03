<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One-off import of the JSON Server seed (`db.json`) keeping the original numeric ids so every
 * cross-reference (orderId, productId, userId, refundId …) stays valid — guide §37.
 *
 * Clean-ups applied (documented in §37 / §42.10):
 *  - plain-text passwords are hashed;
 *  - corrupted money values (e.g. comparePrice 380000000000) are replaced: product 1 gets the
 *    values from its own wishlist snapshot, anything else above the sanity cap becomes 0;
 *  - dangling product references (28, 34, 35, 36, order items for 2/3/17) are dropped / set NULL;
 *  - stray test text in faqs[0] (`###`, `####bhbhhjh`) is stripped;
 *  - users.store_credit is recomputed from the wallet ledger.
 */
class DbJsonImportSeeder extends Seeder
{
    /** Money above this (₹10 crore) is treated as corrupted seed data. */
    private const MONEY_CAP = 100_000_000;

    /** Known-good values for product 1, taken from the wishlist snapshot in db.json. */
    private const PRODUCT_OVERRIDES = [
        1 => ['price' => 32500, 'comparePrice' => 38000, 'variants' => ['v1' => 32500, 'v2' => 33500]],
    ];

    private array $db = [];

    private array $productIds = [];

    public function run(): void
    {
        $path = base_path('db.json');
        if (! is_file($path)) {
            $this->command?->warn('db.json not found — skipping import.');

            return;
        }

        if (DB::table('users')->exists() || DB::table('products')->exists() || DB::table('orders')->exists()) {
            $this->command?->warn('Database already contains data — skipping db.json import.');

            return;
        }

        $this->db = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () {
            $this->users();
            $this->admins();
            $this->categories();
            $this->products();
            $this->coupons();
            $this->shippingMethods();
            $this->orders();
            $this->returns();
            $this->payments();
            $this->refunds();
            $this->walletTransactions();
            $this->cart();
            $this->wishlist();
            $this->reviews();
            $this->leads();
            $this->banners();
            $this->faqs();
            $this->settings();
        });

        $this->command?->info('db.json imported.');
    }

    /* ------------------------------------------------------------------ helpers */

    private function ts(?string $iso, ?string $fallback = null): string
    {
        $value = $iso ?: $fallback;

        return ($value ? Carbon::parse($value)->utc() : now())->format('Y-m-d H:i:s.v');
    }

    private function tsOrNull(?string $iso): ?string
    {
        return $iso ? Carbon::parse($iso)->utc()->format('Y-m-d H:i:s.v') : null;
    }

    private function money(mixed $value): int
    {
        $value = (int) round((float) ($value ?? 0));

        return ($value < 0 || $value > self::MONEY_CAP) ? 0 : $value;
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function productExists(mixed $id): bool
    {
        return $id !== null && in_array((int) $id, $this->productIds, true);
    }

    private function orderExists(mixed $id): bool
    {
        return $id !== null && DB::table('orders')->where('id', (int) $id)->exists();
    }

    private function userExists(mixed $id): bool
    {
        return $id !== null && DB::table('users')->where('id', (int) $id)->exists();
    }

    /* ------------------------------------------------------------------ tables */

    private function users(): void
    {
        foreach ($this->db['users'] ?? [] as $user) {
            DB::table('users')->insert([
                'id' => $user['id'],
                'email' => mb_strtolower(trim($user['email'])),
                'password' => Hash::make($user['password']),
                'first_name' => $user['firstName'] ?? '',
                'last_name' => $user['lastName'] ?? '',
                'phone' => ($user['phone'] ?? '') === '' ? null : $user['phone'],
                'avatar' => $user['avatar'] ?? null,
                'is_active' => (bool) ($user['isActive'] ?? true),
                'store_credit' => $this->money($user['storeCredit'] ?? 0),
                'created_at' => $this->ts($user['createdAt'] ?? null),
                'updated_at' => $this->ts($user['updatedAt'] ?? null, $user['createdAt'] ?? null),
            ]);

            foreach (array_values($user['addresses'] ?? []) as $i => $address) {
                DB::table('user_addresses')->insert([
                    'user_id' => $user['id'],
                    'label' => $address['label'] ?? 'Home',
                    'first_name' => $address['firstName'] ?? $user['firstName'] ?? '',
                    'last_name' => $address['lastName'] ?? $user['lastName'] ?? '',
                    'phone' => $address['phone'] ?? '',
                    'address_line1' => $address['addressLine1'] ?? '',
                    'address_line2' => ($address['addressLine2'] ?? '') === '' ? null : $address['addressLine2'],
                    'city' => $address['city'] ?? '',
                    'state' => $address['state'] ?? '',
                    'postal_code' => $address['postalCode'] ?? '',
                    'country' => $address['country'] ?? 'India',
                    'is_default' => (bool) ($address['isDefault'] ?? $i === 0),
                    'sort_order' => $i,
                ]);
            }
        }
    }

    private function admins(): void
    {
        foreach ($this->db['admins'] ?? [] as $admin) {
            DB::table('admins')->insert([
                'id' => $admin['id'],
                'email' => mb_strtolower(trim($admin['email'])),
                'password' => Hash::make($admin['password']),
                'first_name' => $admin['firstName'] ?? 'Admin',
                'last_name' => $admin['lastName'] ?? '',
                'role' => $admin['role'] ?? 'admin',
                'is_active' => (bool) ($admin['isActive'] ?? true),
                'created_at' => $this->ts($admin['createdAt'] ?? null),
                'updated_at' => $this->ts($admin['updatedAt'] ?? null, $admin['createdAt'] ?? null),
            ]);
        }
    }

    private function categories(): void
    {
        $rows = $this->db['categories'] ?? [];
        // parents first so the self-referencing FK is satisfied
        usort($rows, fn ($a, $b) => ($a['parentId'] === null ? 0 : 1) <=> ($b['parentId'] === null ? 0 : 1));

        foreach ($rows as $category) {
            DB::table('categories')->insert([
                'id' => $category['id'],
                'name' => $category['name'],
                'slug' => $category['slug'],
                'description' => $category['description'] ?? null,
                'image' => ($category['image'] ?? '') === '' ? null : $category['image'],
                'parent_id' => $category['parentId'] ?? null,
                'is_active' => (bool) ($category['isActive'] ?? true),
                'sort_order' => (int) ($category['sortOrder'] ?? 0),
                'show_in_main_menu' => (bool) ($category['showInMainMenu'] ?? false),
                'menu_order' => (int) ($category['menuOrder'] ?? 0),
                'created_at' => $this->ts($category['createdAt'] ?? null),
                'updated_at' => $this->ts($category['updatedAt'] ?? null, $category['createdAt'] ?? null),
            ]);
        }
    }

    private function products(): void
    {
        $products = $this->db['products'] ?? [];
        $this->productIds = array_map(fn ($p) => (int) $p['id'], $products);

        foreach ($products as $product) {
            $override = self::PRODUCT_OVERRIDES[$product['id']] ?? [];
            $dims = $product['dimensions'] ?? null;

            DB::table('products')->insert([
                'id' => $product['id'],
                'name' => $product['name'],
                'slug' => $product['slug'],
                'sku' => ($product['sku'] ?? '') === '' ? null : $product['sku'],
                'short_description' => $product['shortDescription'] ?? null,
                'description' => $product['description'] ?? null,
                'category_id' => $product['categoryId'] ?? null,
                'brand' => $product['brand'] ?? null,
                'price' => $this->money($override['price'] ?? $product['price'] ?? 0),
                'compare_price' => $this->money($override['comparePrice'] ?? $product['comparePrice'] ?? 0),
                'cost_price' => $this->money($product['costPrice'] ?? 0),
                'stock' => (int) ($product['stock'] ?? 0),
                'low_stock_threshold' => (int) ($product['lowStockThreshold'] ?? 10),
                'weight' => (float) ($product['weight'] ?? 0),
                'dim_length' => is_array($dims) ? ($dims['length'] ?? null) : null,
                'dim_width' => is_array($dims) ? ($dims['width'] ?? null) : null,
                'dim_height' => is_array($dims) ? ($dims['height'] ?? null) : null,
                'tags' => $this->json(array_values($product['tags'] ?? [])),
                'featured' => (bool) ($product['featured'] ?? false),
                'trending' => (bool) ($product['trending'] ?? false),
                'hot' => (bool) ($product['hot'] ?? false),
                'is_active' => (bool) ($product['isActive'] ?? true),
                'rating' => round((float) ($product['rating'] ?? 0), 1),
                'total_reviews' => (int) ($product['totalReviews'] ?? 0),
                'meta_title' => $product['metaTitle'] ?? null,
                'meta_description' => $product['metaDescription'] ?? null,
                'created_at' => $this->ts($product['createdAt'] ?? null),
                'updated_at' => $this->ts($product['updatedAt'] ?? null, $product['createdAt'] ?? null),
            ]);

            foreach (array_values($product['images'] ?? []) as $i => $url) {
                DB::table('product_images')->insert(['product_id' => $product['id'], 'url' => $url, 'sort_order' => $i]);
            }

            foreach (array_values($product['variants'] ?? []) as $i => $variant) {
                DB::table('product_variants')->insert([
                    'product_id' => $product['id'],
                    'variant_key' => (string) $variant['id'],
                    'name' => $variant['name'],
                    'price' => $this->money($override['variants'][$variant['id']] ?? $variant['price'] ?? 0),
                    'stock' => (int) ($variant['stock'] ?? 0),
                    'sku' => ($variant['sku'] ?? '') === '' ? null : $variant['sku'],
                    'attributes' => isset($variant['attributes']) ? $this->json($variant['attributes']) : null,
                    'swatch_hex' => $variant['swatchHex'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        }

        // Links after every product exists; dangling ids are dropped.
        foreach ($products as $product) {
            foreach (['relatedProductIds' => 'related', 'frequentlyBoughtTogetherIds' => 'fbt'] as $key => $type) {
                $order = 0;
                foreach (array_unique($product[$key] ?? []) as $linked) {
                    if ($this->productExists($linked) && (int) $linked !== (int) $product['id']) {
                        DB::table('product_links')->insert([
                            'product_id' => $product['id'], 'linked_product_id' => (int) $linked, 'type' => $type, 'sort_order' => $order++,
                        ]);
                    }
                }
            }
        }
    }

    private function coupons(): void
    {
        foreach ($this->db['coupons'] ?? [] as $coupon) {
            DB::table('coupons')->insert([
                'id' => $coupon['id'],
                'code' => mb_strtoupper(trim($coupon['code'])),
                'description' => $coupon['description'] ?? null,
                'type' => $coupon['type'],
                'value' => $this->money($coupon['value'] ?? 0),
                'min_order_amount' => $this->money($coupon['minOrderAmount'] ?? 0),
                'max_discount' => isset($coupon['maxDiscount']) ? $this->money($coupon['maxDiscount']) : null,
                'usage_limit' => $coupon['usageLimit'] ?? null,
                'used_count' => (int) ($coupon['usedCount'] ?? 0),
                'per_user_limit' => $coupon['perUserLimit'] ?? null,
                'is_active' => (bool) ($coupon['isActive'] ?? true),
                'expires_at' => $this->tsOrNull($coupon['expiresAt'] ?? null),
                'created_at' => $this->ts($coupon['createdAt'] ?? null),
                'updated_at' => $this->ts($coupon['updatedAt'] ?? null, $coupon['createdAt'] ?? null),
            ]);
        }
    }

    private function shippingMethods(): void
    {
        foreach ($this->db['shipping_methods'] ?? [] as $method) {
            DB::table('shipping_methods')->insert([
                'id' => $method['id'],
                'name' => $method['name'],
                'carrier' => $method['carrier'] ?? null,
                'description' => $method['description'] ?? null,
                'rate_type' => $method['rateType'] ?? 'flat',
                'flat_rate' => $this->money($method['flatRate'] ?? 0),
                'free_above' => isset($method['freeAbove']) ? $this->money($method['freeAbove']) : null,
                'estimated_days' => isset($method['estimatedDays']) ? (string) $method['estimatedDays'] : null,
                'is_active' => (bool) ($method['isActive'] ?? true),
                'created_at' => $this->ts($method['createdAt'] ?? null),
                'updated_at' => $this->ts($method['updatedAt'] ?? null, $method['createdAt'] ?? null),
            ]);
        }
    }

    private function orders(): void
    {
        $coupons = collect($this->db['coupons'] ?? [])->keyBy(fn ($c) => mb_strtoupper(trim($c['code'])));

        foreach ($this->db['orders'] ?? [] as $order) {
            $code = isset($order['couponCode']) && $order['couponCode'] !== null ? mb_strtoupper(trim($order['couponCode'])) : null;

            DB::table('orders')->insert([
                'id' => $order['id'],
                'order_number' => $order['orderNumber'],
                'user_id' => $this->userExists($order['userId'] ?? null) ? $order['userId'] : null,
                'coupon_id' => $code && $coupons->has($code) ? $coupons[$code]['id'] : null,
                'coupon_code' => $code,
                'coupon_restored' => (bool) ($order['couponRestored'] ?? false),
                'subtotal' => $this->money($order['subtotal'] ?? 0),
                'discount_amount' => $this->money($order['discountAmount'] ?? 0),
                'shipping_amount' => $this->money($order['shippingAmount'] ?? 0),
                'shipping_method_id' => null,
                'tax_amount' => $this->money($order['taxAmount'] ?? 0),
                'cod_fee' => $this->money($order['codFee'] ?? 0),
                'total' => $this->money($order['total'] ?? 0),
                'store_credit_used' => $this->money($order['storeCreditUsed'] ?? 0),
                'store_credit_returned' => (bool) ($order['storeCreditReturned'] ?? false),
                'amount_payable' => $this->money($order['amountPayable'] ?? $order['total'] ?? 0),
                'payment_method' => $order['paymentMethod'] ?? 'cod',
                'payment_status' => $order['paymentStatus'] ?? 'pending',
                'fulfillment_status' => $order['fulfillmentStatus'] ?? 'unfulfilled',
                'shipping_status' => $order['shippingStatus'] ?? 'pending',
                'tracking_number' => $order['trackingNumber'] ?? null,
                'tracking_url' => $order['trackingUrl'] ?? null,
                'shiprocket_order_id' => $order['shiprocketOrderId'] ?? null,
                'notes' => $order['notes'] ?? null,
                'shipping_address' => $this->json($order['shippingAddress'] ?? []),
                'billing_address' => $this->json($order['billingAddress'] ?? $order['shippingAddress'] ?? []),
                'cancel_reason' => $order['cancelReason'] ?? null,
                'cancelled_at' => $this->tsOrNull($order['cancelledAt'] ?? null),
                'delivered_at' => $this->tsOrNull($order['deliveredAt'] ?? null),
                'refund_status' => $order['refundStatus'] ?? null,
                'refund_method' => $order['refundMethod'] ?? null,
                'refunded_amount' => $this->money($order['refundedAmount'] ?? 0),
                'refund_completed_at' => $this->tsOrNull($order['refundCompletedAt'] ?? null),
                'pending_refund' => $this->json($order['pendingRefund'] ?? null),
                'recall' => $this->json($order['recall'] ?? null),
                'created_at' => $this->ts($order['createdAt'] ?? null),
                'updated_at' => $this->ts($order['updatedAt'] ?? null, $order['createdAt'] ?? null),
            ]);

            foreach ($order['items'] ?? [] as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $order['id'],
                    'product_id' => $this->productExists($item['productId'] ?? null) ? $item['productId'] : null,
                    'variant_key' => $item['variantId'] ?? null,
                    'variant_name' => $item['variantName'] ?? null,
                    'name' => $item['name'] ?? '',
                    'image' => $item['image'] ?? null,
                    'sku' => ($item['sku'] ?? '') === '' ? null : $item['sku'],
                    'price' => $this->money($item['price'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'subtotal' => $this->money($item['subtotal'] ?? 0),
                ]);
            }

            foreach ($order['statusHistory'] ?? [] as $event) {
                DB::table('order_status_history')->insert([
                    'order_id' => $order['id'],
                    'at' => $this->ts($event['at'] ?? null, $order['createdAt'] ?? null),
                    'by' => $event['by'] ?? 'System',
                    'action' => $event['action'] ?? '',
                    'note' => ($event['note'] ?? '') === '' ? null : $event['note'],
                ]);
            }
        }
    }

    private function returns(): void
    {
        foreach ($this->db['returns'] ?? [] as $return) {
            if (! $this->orderExists($return['orderId'] ?? null)) {
                continue;
            }

            DB::table('returns')->insert([
                'id' => $return['id'],
                'return_number' => $return['returnNumber'],
                'order_id' => $return['orderId'],
                'user_id' => $this->userExists($return['userId'] ?? null) ? $return['userId'] : null,
                'reason' => $return['reason'] ?? 'other',
                'reason_details' => $return['reasonDetails'] ?? null,
                'status' => $return['status'] ?? 'requested',
                'reject_reason' => $return['rejectReason'] ?? null,
                'refund_amount' => $this->money($return['refundAmount'] ?? 0),
                'refund_status' => $return['refundStatus'] ?? 'pending',
                'refund_method' => $return['refundMethod'] ?? 'original_payment',
                'deduction_amount' => $this->money($return['deductionAmount'] ?? 0),
                'restocked' => (bool) ($return['restocked'] ?? false),
                'store_credit_credited' => (bool) ($return['storeCreditCredited'] ?? false),
                'return_tracking_number' => $return['returnTrackingNumber'] ?? null,
                'return_tracking_url' => $return['returnTrackingUrl'] ?? null,
                'return_carrier' => $return['returnCarrier'] ?? null,
                'pickup_scheduled_at' => $this->tsOrNull($return['pickupScheduledAt'] ?? null),
                'images' => $this->json([]),
                'notes' => $return['notes'] ?? null,
                'created_at' => $this->ts($return['createdAt'] ?? null),
                'updated_at' => $this->ts($return['updatedAt'] ?? null, $return['createdAt'] ?? null),
            ]);

            foreach ($return['items'] ?? [] as $item) {
                DB::table('return_items')->insert([
                    'return_id' => $return['id'],
                    'product_id' => $this->productExists($item['productId'] ?? null) ? $item['productId'] : null,
                    'variant_key' => $item['variantId'] ?? null,
                    'variant_name' => $item['variantName'] ?? null,
                    'name' => $item['name'] ?? '',
                    'sku' => ($item['sku'] ?? '') === '' ? null : $item['sku'],
                    'price' => $this->money($item['price'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'subtotal' => $this->money($item['subtotal'] ?? 0),
                ]);
            }

            foreach ($return['statusHistory'] ?? [] as $event) {
                DB::table('return_status_history')->insert([
                    'return_id' => $return['id'],
                    'at' => $this->ts($event['at'] ?? null, $return['createdAt'] ?? null),
                    'by' => $event['by'] ?? 'System',
                    'action' => $event['action'] ?? '',
                    'note' => ($event['note'] ?? '') === '' ? null : $event['note'],
                ]);
            }
        }
    }

    private function payments(): void
    {
        foreach ($this->db['payments'] ?? [] as $payment) {
            if (! $this->orderExists($payment['orderId'] ?? null)) {
                continue;
            }

            DB::table('payments')->insert([
                'id' => $payment['id'],
                'order_id' => $payment['orderId'],
                'user_id' => $this->userExists($payment['userId'] ?? null) ? $payment['userId'] : null,
                'amount' => $this->money($payment['amount'] ?? 0),
                'currency' => $payment['currency'] ?? 'INR',
                'payment_method' => $payment['paymentMethod'] ?? 'cod',
                'gateway' => $payment['gateway'] ?? 'cod',
                'transaction_id' => $payment['transactionId'] ?? null,
                'gateway_order_id' => $payment['gatewayOrderId'] ?? null,
                'status' => $payment['status'] ?? 'pending',
                'gateway_response' => $this->json($payment['gatewayResponse'] ?? []),
                'refund_amount' => $this->money($payment['refundAmount'] ?? 0),
                'refund_reason' => $payment['refundReason'] ?? null,
                'pending_refund' => $this->json($payment['pendingRefund'] ?? null),
                'store_credit_applied' => $this->money($payment['storeCreditApplied'] ?? 0),
                'created_at' => $this->ts($payment['createdAt'] ?? null),
                'updated_at' => $this->ts($payment['updatedAt'] ?? null, $payment['createdAt'] ?? null),
            ]);

            foreach ($payment['refunds'] ?? [] as $refund) {
                DB::table('payment_refunds')->insert([
                    'payment_id' => $payment['id'],
                    'ref_key' => (string) ($refund['id'] ?? 'ref_'.uniqid()),
                    'amount' => $this->money($refund['amount'] ?? 0),
                    'reason' => $refund['reason'] ?? null,
                    'at' => $this->ts($refund['at'] ?? null, $payment['updatedAt'] ?? null),
                    'by' => $refund['by'] ?? 'System',
                ]);
            }
        }
    }

    private function refunds(): void
    {
        foreach ($this->db['refunds'] ?? [] as $refund) {
            DB::table('refunds')->insert([
                'id' => $refund['id'],
                'refund_number' => $refund['refundNumber'],
                'type' => $refund['type'] ?? 'order_refund',
                'order_id' => $this->orderExists($refund['orderId'] ?? null) ? $refund['orderId'] : null,
                'return_id' => isset($refund['returnId']) && DB::table('returns')->where('id', $refund['returnId'])->exists() ? $refund['returnId'] : null,
                'payment_id' => isset($refund['paymentId']) && DB::table('payments')->where('id', $refund['paymentId'])->exists() ? $refund['paymentId'] : null,
                'amount' => $this->money($refund['amount'] ?? 0),
                'method' => $refund['method'] ?? 'original_payment',
                'reason' => $refund['reason'] ?? null,
                'reference' => $refund['reference'] ?? null,
                'status' => $refund['status'] ?? 'pending',
                'coupon_restored' => (bool) ($refund['couponRestored'] ?? false),
                'initiated_at' => $this->ts($refund['initiatedAt'] ?? null, $refund['createdAt'] ?? null),
                'settled_at' => $this->tsOrNull($refund['settledAt'] ?? null),
                'by' => $refund['by'] ?? 'System',
                'created_at' => $this->ts($refund['createdAt'] ?? null),
                'updated_at' => $this->ts($refund['updatedAt'] ?? null, $refund['createdAt'] ?? null),
            ]);
        }
    }

    private function walletTransactions(): void
    {
        foreach ($this->db['walletTransactions'] ?? [] as $tx) {
            if (! $this->userExists($tx['userId'] ?? null)) {
                continue;
            }
            DB::table('wallet_transactions')->insert([
                'id' => $tx['id'],
                'user_id' => $tx['userId'],
                'type' => $tx['type'],
                'amount' => $this->money($tx['amount'] ?? 0),
                'reason' => $tx['reason'] ?? null,
                'order_id' => $this->orderExists($tx['orderId'] ?? null) ? $tx['orderId'] : null,
                'refund_id' => isset($tx['refundId']) && DB::table('refunds')->where('id', $tx['refundId'])->exists() ? $tx['refundId'] : null,
                'balance_before' => $this->money($tx['balanceBefore'] ?? 0),
                'balance_after' => $this->money($tx['balanceAfter'] ?? 0),
                'created_at' => $this->ts($tx['createdAt'] ?? null),
            ]);
        }

        // The ledger is the source of truth for the cached balance.
        foreach (DB::table('users')->pluck('id') as $userId) {
            $credits = (int) DB::table('wallet_transactions')->where('user_id', $userId)->where('type', 'credit')->sum('amount');
            $debits = (int) DB::table('wallet_transactions')->where('user_id', $userId)->where('type', 'debit')->sum('amount');
            DB::table('users')->where('id', $userId)->update(['store_credit' => max(0, $credits - $debits)]);
        }
    }

    private function cart(): void
    {
        foreach ($this->db['cart'] ?? [] as $line) {
            if (! $this->productExists($line['productId'] ?? null) || ! $this->userExists($line['userId'] ?? null)) {
                continue;
            }
            DB::table('cart_items')->insert([
                'id' => $line['id'],
                'user_id' => $line['userId'],
                'product_id' => $line['productId'],
                'variant_key' => $line['variantId'] ?? null,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'created_at' => $this->ts($line['createdAt'] ?? null),
                'updated_at' => $this->ts($line['updatedAt'] ?? null),
            ]);
        }
    }

    private function wishlist(): void
    {
        foreach ($this->db['wishlist'] ?? [] as $row) {
            if (! $this->productExists($row['productId'] ?? null) || ! $this->userExists($row['userId'] ?? null)) {
                continue;
            }
            DB::table('wishlist_items')->insert([
                'id' => $row['id'],
                'user_id' => $row['userId'],
                'product_id' => $row['productId'],
                'created_at' => $this->ts($row['addedAt'] ?? $row['createdAt'] ?? null),
            ]);
        }
    }

    private function reviews(): void
    {
        foreach ($this->db['reviews'] ?? [] as $review) {
            if (! $this->productExists($review['productId'] ?? null)) {
                continue;
            }
            DB::table('reviews')->insert([
                'id' => $review['id'],
                'product_id' => $review['productId'],
                'user_id' => $this->userExists($review['userId'] ?? null) ? $review['userId'] : null,
                'order_id' => $this->orderExists($review['orderId'] ?? null) ? $review['orderId'] : null,
                'user_name' => $review['userName'] ?? 'Customer',
                'rating' => max(1, min(5, (int) ($review['rating'] ?? 5))),
                'title' => $review['title'] ?? '',
                'body' => $review['body'] ?? '',
                'status' => $review['status'] ?? 'pending',
                'is_verified_purchase' => (bool) ($review['isVerifiedPurchase'] ?? false),
                'helpful_count' => (int) ($review['helpfulCount'] ?? 0),
                'source' => $review['source'] ?? null,
                'photos' => isset($review['photos']) ? $this->json(array_values($review['photos'])) : null,
                'created_at' => $this->ts($review['createdAt'] ?? null),
                'updated_at' => $this->ts($review['updatedAt'] ?? null, $review['createdAt'] ?? null),
            ]);
        }
    }

    private function leads(): void
    {
        foreach ($this->db['leads'] ?? [] as $lead) {
            DB::table('leads')->insert([
                'id' => $lead['id'],
                'type' => $lead['type'] ?? 'contact',
                'name' => $lead['name'] ?? null,
                'email' => mb_strtolower(trim($lead['email'] ?? '')),
                'phone' => ($lead['phone'] ?? '') === '' ? null : $lead['phone'],
                'order_number' => ($lead['orderNumber'] ?? '') === '' ? null : $lead['orderNumber'],
                'category' => $lead['category'] ?? null,
                'subject' => $lead['subject'] ?? null,
                'message' => $lead['message'] ?? null,
                'status' => $lead['status'] ?? (($lead['type'] ?? 'contact') === 'newsletter' ? 'subscribed' : 'new'),
                'notes' => $lead['notes'] ?? '',
                'created_at' => $this->ts($lead['createdAt'] ?? null),
                'updated_at' => $this->ts($lead['updatedAt'] ?? null, $lead['createdAt'] ?? null),
            ]);
        }
    }

    private function banners(): void
    {
        foreach ($this->db['banners'] ?? [] as $banner) {
            DB::table('banners')->insert([
                'id' => $banner['id'],
                'title' => $banner['title'],
                'subtitle' => $banner['subtitle'] ?? '',
                'eyebrow' => $banner['eyebrow'] ?? '',
                'cta' => $banner['cta'] ?? '',
                'link' => $banner['link'] ?? '/products',
                'secondary_cta_label' => $banner['secondaryCtaLabel'] ?? '',
                'secondary_cta_link' => $banner['secondaryCtaLink'] ?? '',
                'background_type' => $banner['backgroundType'] ?? 'gradient',
                'gradient' => $banner['gradient'] ?? null,
                'image' => $banner['image'] ?? '',
                'image_position' => $banner['imagePosition'] ?? 'right center',
                'video_url' => $banner['videoUrl'] ?? '',
                'video_poster' => $banner['videoPoster'] ?? '',
                'overlay_opacity' => $banner['overlayOpacity'] ?? null,
                'text_align' => $banner['textAlign'] ?? 'left',
                'duration_ms' => (int) ($banner['durationMs'] ?? 0),
                'is_active' => (bool) ($banner['isActive'] ?? true),
                'sort_order' => (int) ($banner['sortOrder'] ?? 0),
                'created_at' => $this->ts($banner['createdAt'] ?? null, $banner['updatedAt'] ?? null),
                'updated_at' => $this->ts($banner['updatedAt'] ?? null, $banner['createdAt'] ?? null),
            ]);
        }
    }

    private function faqs(): void
    {
        foreach ($this->db['faqs'] ?? [] as $faq) {
            DB::table('faqs')->insert([
                'id' => $faq['id'],
                'question' => trim(preg_replace('/#+\s*$/', '', $faq['question'] ?? '')),
                'answer' => trim(preg_replace('/#{2,}\S*\s*$/', '', $faq['answer'] ?? '')),
                'placements' => $this->json(array_values($faq['placements'] ?? ['product', 'help', 'home'])),
                'is_active' => (bool) ($faq['isActive'] ?? true),
                'sort_order' => (int) ($faq['sortOrder'] ?? 0),
                'created_at' => $this->ts($faq['createdAt'] ?? null),
                'updated_at' => $this->ts($faq['updatedAt'] ?? null, $faq['createdAt'] ?? null),
            ]);

            foreach (array_unique($faq['productIds'] ?? []) as $productId) {
                if ($this->productExists($productId)) {
                    DB::table('faq_product')->insert(['faq_id' => $faq['id'], 'product_id' => (int) $productId]);
                }
            }
        }
    }

    private function settings(): void
    {
        $settings = $this->db['settings'] ?? [];

        foreach (['store', 'shipping', 'payment', 'notifications', 'seo', 'social'] as $section) {
            $data = $settings[$section] ?? [];
            if ($section === 'shipping') {
                $password = (string) ($data['shiprocketPassword'] ?? '');
                $data['shiprocketPassword'] = $password === '' ? '' : Crypt::encryptString($password);
            }
            DB::table('settings')->updateOrInsert(['section' => $section], ['data' => $this->json($data), 'updated_at' => now()->format('Y-m-d H:i:s.v')]);
        }

        $hero = $this->db['heroConfig'] ?? [];
        $heroUpdated = $hero['updatedAt'] ?? null;
        unset($hero['updatedAt']);
        DB::table('settings')->updateOrInsert(['section' => 'hero_config'], ['data' => $this->json($hero), 'updated_at' => $this->ts($heroUpdated)]);

        $deals = $this->db['dealsConfig'] ?? [];
        $dealsUpdated = $deals['updatedAt'] ?? null;
        unset($deals['updatedAt']);
        $couponIds = DB::table('coupons')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $deals['featuredCouponIds'] = array_values(array_filter(array_map('intval', $deals['featuredCouponIds'] ?? []), fn ($id) => in_array($id, $couponIds, true)));
        $deals['dealOfTheDayIds'] = array_values(array_filter(array_map('intval', $deals['dealOfTheDayIds'] ?? []), fn ($id) => $this->productExists($id)));
        $deals['featuredProductIds'] = array_values(array_filter(array_map('intval', $deals['featuredProductIds'] ?? []), fn ($id) => $this->productExists($id)));
        DB::table('settings')->updateOrInsert(['section' => 'deals_config'], ['data' => $this->json($deals), 'updated_at' => $this->ts($dealsUpdated)]);
    }
}
