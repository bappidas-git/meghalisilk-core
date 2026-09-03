<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\WalletTransaction;

class OrderFlowTest extends ApiTestCase
{
    public function test_place_cod_order_recomputes_everything_server_side(): void
    {
        $headers = $this->customerHeaders();
        CartItem::create(['user_id' => 3, 'product_id' => 1, 'variant_key' => 'v1', 'quantity' => 1]);
        $stockBefore = Product::query()->find(1)->stock;

        $payload = $this->orderPayload(['orderNumber' => 'ORD-CLIENT', 'paymentStatus' => 'paid', 'subtotal' => 1, 'taxAmount' => 0]);
        $response = $this->postJson('/api/v1/orders', $payload, $headers);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $order = $response->json('data');

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{4}$/', $order['orderNumber']);
        $this->assertSame(32500, $order['subtotal']);
        $this->assertSame(1625, $order['taxAmount']);
        $this->assertSame(0, $order['shippingAmount']);
        $this->assertSame(34125, $order['total']);
        $this->assertSame(34125, $order['amountPayable']);
        $this->assertSame('cod', $order['paymentMethod']);
        $this->assertSame('pending', $order['paymentStatus']);
        $this->assertSame('unfulfilled', $order['fulfillmentStatus']);
        $this->assertSame('pending', $order['shippingStatus']);
        $this->assertSame(3, $order['userId']);
        $this->assertSame('Sualkuchi Muga Mekhela Chador — Natural Gold - Natural Gold', $order['items'][0]['name']);
        $this->assertSame('Natural Gold', $order['items'][0]['variantName']);
        $this->assertSame('MEK-MUG-001-NGD', $order['items'][0]['sku']);
        $this->assertSame('Order placed', $order['statusHistory'][0]['action']);
        $this->assertSame('Customer', $order['statusHistory'][0]['by']);
        $this->assertSame('Howly', $order['shippingAddress']['city']);

        $payment = Payment::query()->where('order_id', $order['id'])->first();
        $this->assertSame('cod', $payment->gateway);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(34125, $payment->amount);

        $this->assertSame($stockBefore - 1, Product::query()->find(1)->stock);
        $this->assertSame(4, ProductVariant::query()->where('product_id', 1)->where('variant_key', 'v1')->value('stock'));
        $this->assertSame(0, CartItem::query()->where('user_id', 3)->count());

        $this->getJson('/api/v1/orders/number/'.$order['orderNumber'], $headers)->assertOk()->assertJsonPath('data.id', $order['id']);
        $this->getJson('/api/v1/orders/number/'.$order['id'], $headers)->assertOk();
        $this->getJson('/api/v1/orders/'.$order['id'], $headers)->assertOk();
        $this->getJson('/api/v1/orders/'.$order['id'], $this->customerHeaders($this->customer(1)))->assertStatus(404);
        $this->assertSame($order['id'], $this->getJson('/api/v1/orders', $headers)->json('data.0.id'));
    }

    public function test_place_online_order_with_coupon_and_store_credit(): void
    {
        $headers = $this->customerHeaders();
        $usedBefore = Coupon::query()->code('MUGA500')->value('used_count');

        // subtotal 33500 − 500 coupon = 33000, tax 5% = 1650 → total 34650; wallet 3918 → payable 30732
        $payload = $this->orderPayload([
            'items' => [['productId' => 1, 'variantId' => 'v2', 'quantity' => 1]],
            'couponCode' => 'muga500', 'paymentMethod' => 'upi', 'paymentStatus' => 'paid',
            'storeCreditUsed' => 1000, 'total' => 34650,
        ]);
        $response = $this->postJson('/api/v1/orders', $payload, $headers)->assertStatus(201);
        $order = $response->json('data');

        $this->assertSame(500, $order['discountAmount']);
        $this->assertSame('MUGA500', $order['couponCode']);
        $this->assertSame(1650, $order['taxAmount']);
        $this->assertSame(34650, $order['total']);
        $this->assertSame(1000, $order['storeCreditUsed']);
        $this->assertSame(33650, $order['amountPayable']);
        $this->assertSame('pending', $order['paymentStatus'], 'online orders are pending until the admin marks them paid (§22.3 option A)');

        $this->assertSame($usedBefore + 1, Coupon::query()->code('MUGA500')->value('used_count'));
        $this->assertSame(2918, $this->getJson('/api/v1/wallet/balance', $headers)->json('data.balance'));
        $debit = WalletTransaction::query()->where('order_id', $order['id'])->where('type', 'debit')->first();
        $this->assertSame(1000, $debit->amount);
        $this->assertSame(2918, $debit->balance_after);
        $this->assertSame('debit', $this->getJson('/api/v1/wallet/transactions', $headers)->json('data.0.type'));

        $payment = Payment::query()->where('order_id', $order['id'])->first();
        $this->assertSame('razorpay', $payment->gateway);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(1000, $payment->store_credit_applied);

        // per-user limit is now exhausted
        $this->postJson('/api/v1/coupons/validate', ['code' => 'MUGA500', 'orderAmount' => 8999, 'userId' => 3], $headers)
            ->assertStatus(422)->assertJsonPath('message', 'You have already used this coupon');
    }

    public function test_order_validation_failures(): void
    {
        $headers = $this->customerHeaders();

        $this->postJson('/api/v1/orders', $this->orderPayload(['total' => 999]), $headers)
            ->assertStatus(422)->assertJsonPath('message', 'Prices have changed, please review your cart.');

        $this->postJson('/api/v1/orders', $this->orderPayload(['shippingAmount' => 12345]), $headers)->assertStatus(422);

        $this->postJson('/api/v1/orders', $this->orderPayload(['items' => [['productId' => 1, 'variantId' => 'v1', 'quantity' => 99]], 'total' => null]), $headers)
            ->assertStatus(422)->assertJsonPath('message', 'Only 5 left in stock for Sualkuchi Muga Mekhela Chador — Natural Gold - Natural Gold.');

        $this->postJson('/api/v1/orders', $this->orderPayload(['couponCode' => 'NOPE', 'total' => null]), $headers)
            ->assertStatus(422)->assertJsonPath('message', 'Invalid coupon code');

        $this->postJson('/api/v1/orders', $this->orderPayload(['items' => []]), $headers)->assertStatus(422);
        $this->postJson('/api/v1/orders', $this->orderPayload(['shippingAddress' => ['firstName' => 'x']]), $headers)->assertStatus(422);
        $this->postJson('/api/v1/orders', $this->orderPayload())->assertStatus(401);
    }

    public function test_coupon_validation_messages(): void
    {
        $this->postJson('/api/v1/coupons/validate', ['code' => 'MUGA500', 'orderAmount' => 8999, 'userId' => null])
            ->assertOk()->assertJsonPath('data.code', 'MUGA500')->assertJsonPath('data.type', 'fixed')->assertJsonPath('data.minOrderAmount', 5000);
        $this->postJson('/api/v1/coupons/validate', ['code' => 'NOSUCHCODE', 'orderAmount' => 8999, 'userId' => null])
            ->assertStatus(422)->assertJsonPath('message', 'Invalid coupon code');
        $this->postJson('/api/v1/coupons/validate', ['code' => 'muga500', 'orderAmount' => 10, 'userId' => null])
            ->assertStatus(422)->assertJsonPath('message', 'Minimum order amount is ₹5,000');

        Coupon::query()->code('MUGA500')->update(['expires_at' => now()->subDay()]);
        $this->postJson('/api/v1/coupons/validate', ['code' => 'MUGA500', 'orderAmount' => 8999])
            ->assertStatus(422)->assertJsonPath('message', 'Coupon has expired');
        Coupon::query()->code('MUGA500')->update(['expires_at' => now()->addYear(), 'usage_limit' => 1, 'used_count' => 1]);
        $this->postJson('/api/v1/coupons/validate', ['code' => 'MUGA500', 'orderAmount' => 8999])
            ->assertStatus(422)->assertJsonPath('message', 'Coupon usage limit reached');
    }

    public function test_customer_cancellation_cascade(): void
    {
        $headers = $this->customerHeaders();
        $order = $this->postJson('/api/v1/orders', $this->orderPayload([
            'items' => [['productId' => 1, 'variantId' => 'v2', 'quantity' => 1]],
            'couponCode' => 'MUGA500', 'storeCreditUsed' => 1000, 'total' => 34650,
        ]), $headers)->assertStatus(201)->json('data');

        $this->assertSame(3, ProductVariant::query()->where('product_id', 1)->where('variant_key', 'v2')->value('stock'));

        $response = $this->postJson("/api/v1/orders/{$order['id']}/cancel", ['reason' => 'Cancelled by customer'], $headers)->assertOk();
        $cancelled = $response->json('data');

        $this->assertSame('cancelled', $cancelled['fulfillmentStatus']);
        $this->assertSame('voided', $cancelled['paymentStatus']);
        $this->assertSame('Cancelled by customer', $cancelled['cancelReason']);
        $this->assertTrue($cancelled['storeCreditReturned']);
        $this->assertTrue($cancelled['couponRestored']);
        $actions = array_column($cancelled['statusHistory'], 'action');
        $this->assertSame(['Order placed', 'Order cancelled', 'Payment voided', 'Store credit returned', 'Coupon usage restored'], $actions);
        $this->assertSame('Cash on delivery not collected', $cancelled['statusHistory'][2]['note']);
        $this->assertSame('₹1,000 added back to your wallet', $cancelled['statusHistory'][3]['note']);

        $this->assertSame(4, ProductVariant::query()->where('product_id', 1)->where('variant_key', 'v2')->value('stock'));
        $this->assertSame(3918, $this->getJson('/api/v1/wallet/balance', $headers)->json('data.balance'));
        $this->assertSame(0, Coupon::query()->code('MUGA500')->value('used_count'));
        $this->assertSame('voided', Payment::query()->where('order_id', $order['id'])->value('status'));

        $this->postJson("/api/v1/orders/{$order['id']}/cancel", [], $headers)
            ->assertStatus(409)->assertJsonPath('message', 'This order can no longer be cancelled.');
    }

    public function test_admin_order_updates_and_refund_lifecycle(): void
    {
        $customer = $this->customerHeaders();
        $admin = $this->adminHeaders();
        $order = $this->postJson('/api/v1/orders', $this->orderPayload(), $customer)->assertStatus(201)->json('data');
        $id = $order['id'];

        $list = $this->getJson('/api/v1/admin/orders', $admin)->assertOk()->json('data');
        $this->assertSame($id, $list[0]['id']);
        $this->assertSame('mail4bappidas@gmail.com', $list[0]['customerEmail']);
        $this->assertSame('Bappi Das', $list[0]['customerName']);
        $this->assertCount(count(array_filter($list, fn ($o) => $o['userId'] === 3)), $this->getJson('/api/v1/admin/orders?userId=3', $admin)->json('data'));

        $this->patchJson("/api/v1/admin/orders/$id", [
            'fulfillmentStatus' => 'fulfilled', 'shippingStatus' => 'shipped', 'trackingNumber' => 'SHIP123456789IN',
            'trackingUrl' => 'https://shiprocket.co/tracking/SHIP123456789IN', 'notes' => 'Packed',
            'event' => ['action' => 'Fulfilled & shipped', 'note' => 'Tracking SHIP123456789IN · tracking link added'],
        ], $admin)->assertOk()
            ->assertJsonPath('data.shippingStatus', 'shipped')
            ->assertJsonPath('data.statusHistory.1.action', 'Fulfilled & shipped')
            ->assertJsonPath('data.statusHistory.1.by', 'Admin User');

        $this->patchJson("/api/v1/admin/orders/$id", ['trackingUrl' => 'not a url'], $admin)->assertStatus(422);

        $this->patchJson("/api/v1/admin/orders/$id", ['paymentStatus' => 'paid', 'notes' => 'Packed', 'event' => ['action' => 'Payment marked as paid']], $admin)
            ->assertOk()->assertJsonPath('data.paymentStatus', 'paid');
        $payment = Payment::query()->where('order_id', $id)->first();
        $this->assertSame('captured', $payment->status);
        $this->assertStringStartsWith('manual_', $payment->transaction_id);

        $this->patchJson("/api/v1/admin/orders/$id", ['shippingAddress' => ['id' => 1, 'firstName' => 'John', 'lastName' => 'Doe', 'phone' => '+91 9876543210',
            'addressLine1' => '123 Main Street', 'addressLine2' => 'Near BH College', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'postalCode' => '400001', 'country' => 'India'],
            'event' => ['action' => 'Shipping address updated']], $admin)
            ->assertOk()->assertJsonPath('data.shippingAddress.city', 'Mumbai');

        // refund: initiate → fail → initiate (store credit) → complete
        $this->postJson("/api/v1/admin/orders/$id/refund/initiate", ['amount' => 999999, 'method' => 'original_payment', 'reason' => 'x', 'reference' => null], $admin)
            ->assertStatus(422)->assertJsonPath('message', "Refund can't exceed ₹34,125");

        $this->postJson("/api/v1/admin/orders/$id/refund/initiate", ['amount' => 1000, 'method' => 'original_payment', 'reason' => 'Price adjustment', 'reference' => null], $admin)
            ->assertOk()
            ->assertJsonPath('data.refundStatus', 'processing')
            ->assertJsonPath('data.pendingRefund.amount', 1000)
            ->assertJsonPath('data.pendingRefund.by', 'Admin User');
        $this->assertSame('refund_pending', $payment->fresh()->status);
        $this->assertSame('pending', Refund::query()->where('order_id', $id)->latest('id')->value('status'));
        $this->postJson("/api/v1/admin/orders/$id/refund/complete", [], $admin)->assertOk(); // complete works while processing
        $this->postJson("/api/v1/admin/orders/$id/refund/complete", [], $admin)->assertStatus(409);

        $this->postJson("/api/v1/admin/orders/$id/refund/initiate", ['amount' => 1000, 'method' => 'original_payment', 'reason' => 'Again', 'reference' => 'UTR1'], $admin)->assertOk();
        $this->postJson("/api/v1/admin/orders/$id/refund/fail", ['note' => ''], $admin)->assertOk()->assertJsonPath('data.refundStatus', 'failed');
        $this->assertSame('partially_refunded', $payment->fresh()->status);
        $this->assertSame('failed', Refund::query()->where('order_id', $id)->latest('id')->value('status'));

        $this->postJson("/api/v1/admin/orders/$id/refund/initiate", ['amount' => 1000, 'method' => 'store_credit', 'reason' => 'Price adjustment', 'reference' => 'UTR123456'], $admin)->assertOk();
        $completed = $this->postJson("/api/v1/admin/orders/$id/refund/complete", [], $admin)->assertOk()->json('data');
        $this->assertSame('completed', $completed['refundStatus']);
        $this->assertSame(2000, $completed['refundedAmount']);
        $this->assertSame('partially_refunded', $completed['paymentStatus']);
        $this->assertNull($completed['pendingRefund']);
        $this->assertSame(2000, $payment->fresh()->refund_amount);
        $this->assertCount(2, $payment->fresh()->refundEntries);
        $this->assertSame(4918, $this->getJson('/api/v1/wallet/balance', $customer)->json('data.balance'));

        $ledger = $this->getJson('/api/v1/admin/refunds', $admin)->assertOk()->json('data');
        $this->assertSame('completed', $ledger[0]['status']);
        $this->assertSame($order['orderNumber'], $ledger[0]['orderNumber']);

        // admin cancel with recall + refund on a shipped (not delivered) order
        $cancelled = $this->postJson("/api/v1/admin/orders/$id/cancel", [
            'reason' => 'Customer unreachable', 'restock' => true, 'refund' => ['method' => 'original_payment'],
            'recall' => ['trackingNumber' => 'RETN-SR-1', 'trackingUrl' => 'https://shiprocket.co/tracking/RETN-SR-1'],
        ], $admin)->assertOk()->json('data');
        $this->assertSame('cancelled', $cancelled['fulfillmentStatus']);
        $this->assertSame('recalled', $cancelled['shippingStatus']);
        $this->assertSame('RETN-SR-1', $cancelled['recall']['trackingNumber']);
        $this->assertSame('processing', $cancelled['refundStatus']);
        $this->assertSame(32125, $cancelled['pendingRefund']['amount']);
        $this->assertSame('recall_refund', Refund::query()->where('order_id', $id)->latest('id')->value('type'));
        $this->assertSame(5, ProductVariant::query()->where('product_id', 1)->where('variant_key', 'v1')->value('stock'));

        $this->postJson("/api/v1/admin/orders/$id/cancel", ['reason' => 'again'], $admin)->assertStatus(409);
    }

    public function test_admin_cannot_cancel_delivered_orders(): void
    {
        $admin = $this->adminHeaders();
        $order = $this->postJson('/api/v1/orders', $this->orderPayload(), $this->customerHeaders())->json('data');
        $this->patchJson("/api/v1/admin/orders/{$order['id']}", ['shippingStatus' => 'delivered', 'deliveredAt' => '2026-09-05T06:24:29.130Z', 'event' => ['action' => 'Marked delivered']], $admin)
            ->assertOk()->assertJsonPath('data.deliveredAt', '2026-09-05T06:24:29.130Z');

        $this->postJson("/api/v1/admin/orders/{$order['id']}/cancel", ['reason' => 'x', 'restock' => true], $admin)->assertStatus(409);
    }

    public function test_reviews_are_purchase_gated(): void
    {
        $customer = $this->customerHeaders();
        $admin = $this->adminHeaders();
        $order = $this->postJson('/api/v1/orders', $this->orderPayload(), $customer)->json('data');

        $this->postJson('/api/v1/products/1/reviews', ['rating' => 5, 'title' => 'Great', 'body' => 'Lovely', 'orderId' => $order['id']], $customer)
            ->assertStatus(403)->assertJsonPath('message', 'You can review this product once it has been delivered.');

        $this->patchJson("/api/v1/admin/orders/{$order['id']}", ['shippingStatus' => 'delivered', 'event' => ['action' => 'Marked delivered']], $admin)->assertOk();

        $created = $this->postJson('/api/v1/products/1/reviews', ['rating' => 5, 'title' => 'Great', 'body' => 'Lovely', 'orderId' => $order['id']], $customer)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.userName', 'Bappi D.')
            ->assertJsonPath('data.isVerifiedPurchase', true)
            ->assertJsonPath('data.orderNumber', $order['orderNumber'])
            ->json('data');

        $this->postJson('/api/v1/products/1/reviews', ['rating' => 3, 'title' => 'Edited', 'body' => 'Changed my mind', 'orderId' => null], $customer)
            ->assertOk()->assertJsonPath('data.id', $created['id'])->assertJsonPath('data.rating', 3);

        $mine = $this->getJson('/api/v1/reviews/mine', $customer)->assertOk()->json('data');
        $this->assertSame($created['id'], $mine[0]['id']);

        // approving recomputes the product rating from approved reviews only
        $this->patchJson("/api/v1/admin/reviews/{$created['id']}", ['status' => 'approved'], $admin)->assertOk();
        $product = Product::query()->find(1);
        $approved = $product->reviews()->where('status', 'approved')->get();
        $this->assertSame($approved->count(), $product->total_reviews);
        $this->assertSame(round($approved->avg('rating'), 1), $product->rating);
    }

    public function test_returns_flow_with_refund_cascade(): void
    {
        $customer = $this->customerHeaders();
        $admin = $this->adminHeaders();
        $order = $this->postJson('/api/v1/orders', $this->orderPayload([
            'items' => [['productId' => 1, 'variantId' => 'v2', 'quantity' => 1]],
            'couponCode' => 'MUGA500', 'paymentMethod' => 'upi', 'total' => 34650,
        ]), $customer)->json('data');
        $this->patchJson("/api/v1/admin/orders/{$order['id']}", ['paymentStatus' => 'paid', 'event' => ['action' => 'Payment marked as paid']], $admin)->assertOk();
        $this->patchJson("/api/v1/admin/orders/{$order['id']}", ['shippingStatus' => 'delivered', 'event' => ['action' => 'Marked delivered']], $admin)->assertOk();

        $created = $this->postJson('/api/v1/admin/returns', [
            'orderId' => $order['id'], 'orderNumber' => $order['orderNumber'], 'userId' => 3,
            'items' => [['productId' => 1, 'variantId' => 'v2', 'name' => 'x', 'sku' => 'x', 'price' => 1, 'quantity' => 1, 'subtotal' => 1]],
            'reason' => 'defective', 'reasonDetails' => 'Loose threads', 'refundAmount' => 99999, 'refundMethod' => 'original_payment',
        ], $admin)->assertStatus(201)->json('data');

        $this->assertMatchesRegularExpression('/^RET-\d{8}-\d{4}$/', $created['returnNumber']);
        $this->assertSame('requested', $created['status']);
        $this->assertSame(33000, $created['refundAmount']); // 33500 gross − proportional coupon share 500
        $this->assertSame('Return created', $created['statusHistory'][0]['action']);
        $this->assertSame('Reason: defective', $created['statusHistory'][0]['note']);
        $this->assertSame($order['orderNumber'], $created['orderNumber']);
        $rid = $created['id'];

        $this->postJson('/api/v1/admin/returns', ['orderId' => $order['id'], 'items' => [['productId' => 1, 'variantId' => 'v2', 'quantity' => 5]], 'reason' => 'defective'], $admin)->assertStatus(422);

        $this->patchJson("/api/v1/admin/returns/$rid", ['status' => 'received', 'event' => ['action' => 'x'], 'restock' => false], $admin)->assertStatus(409);
        $this->patchJson("/api/v1/admin/returns/$rid", ['status' => 'approved', 'notes' => 'ok', 'event' => ['action' => 'Return approved'], 'restock' => false], $admin)->assertOk()->assertJsonPath('data.status', 'approved');
        $this->patchJson("/api/v1/admin/returns/$rid", ['status' => 'pickup_scheduled', 'returnTrackingNumber' => 'RETN-1', 'returnTrackingUrl' => 'https://x.co/1', 'returnCarrier' => 'Shiprocket',
            'pickupScheduledAt' => '2026-09-06T00:00:00.000Z', 'event' => ['action' => 'Return pickup scheduled', 'note' => 'x'], 'restock' => false], $admin)
            ->assertOk()->assertJsonPath('data.pickupScheduledAt', '2026-09-06T00:00:00.000Z');
        $this->patchJson("/api/v1/admin/returns/$rid", ['status' => 'in_transit', 'event' => ['action' => 'Return in transit'], 'restock' => false], $admin)->assertOk();
        $this->patchJson("/api/v1/admin/returns/$rid", ['status' => 'received', 'event' => ['action' => 'Items received'], 'restock' => false], $admin)->assertOk();

        $variantStock = ProductVariant::query()->where('product_id', 1)->where('variant_key', 'v2')->value('stock');
        $balanceBefore = $this->getJson('/api/v1/wallet/balance', $customer)->json('data.balance');

        $refunded = $this->patchJson("/api/v1/admin/returns/$rid", [
            'status' => 'refunded', 'refundStatus' => 'processed', 'deductionAmount' => 500, 'refundMethod' => 'store_credit',
            'notes' => 'Restocking fee', 'event' => ['action' => 'Refund processed (₹32,500)', 'note' => '₹500 deducted'], 'restock' => true,
        ], $admin)->assertOk()->json('data');

        $this->assertSame('refunded', $refunded['status']);
        $this->assertTrue($refunded['restocked']);
        $this->assertTrue($refunded['storeCreditCredited']);
        $this->assertSame($variantStock + 1, ProductVariant::query()->where('product_id', 1)->where('variant_key', 'v2')->value('stock'));
        $this->assertSame($balanceBefore + 32500, $this->getJson('/api/v1/wallet/balance', $customer)->json('data.balance'));

        $fresh = Order::query()->find($order['id']);
        $this->assertSame('returned', $fresh->fulfillment_status);
        $this->assertSame('partially_refunded', $fresh->payment_status);
        $this->assertTrue($fresh->coupon_restored);
        $this->assertSame(0, Coupon::query()->code('MUGA500')->value('used_count'));
        $payment = Payment::query()->where('order_id', $order['id'])->first();
        $this->assertSame(32500, $payment->refund_amount);
        $this->assertSame('Return '.$created['returnNumber'], $payment->refundEntries->first()->reason);
        $ledger = Refund::query()->where('return_id', $rid)->first();
        $this->assertSame('return_refund', $ledger->type);
        $this->assertSame('completed', $ledger->status);
        $this->assertTrue($ledger->coupon_restored);

        $this->patchJson("/api/v1/admin/returns/$rid", ['status' => 'rejected', 'rejectReason' => 'late', 'event' => ['action' => 'Return rejected'], 'restock' => false], $admin)
            ->assertStatus(409)->assertJsonStructure(['message']);

        $this->assertSame($rid, $this->getJson('/api/v1/admin/returns', $admin)->assertOk()->json('data.0.id'));
        $this->getJson("/api/v1/admin/returns/$rid", $admin)->assertOk()->assertJsonPath('data.returnNumber', $created['returnNumber']);
    }

    public function test_customer_returns_endpoints(): void
    {
        $customer = $this->customerHeaders();
        $order = $this->postJson('/api/v1/orders', $this->orderPayload(), $customer)->json('data');

        $created = $this->postJson('/api/v1/returns', [
            'orderId' => $order['id'], 'items' => [['productId' => 1, 'variantId' => 'v1', 'quantity' => 1]],
            'reason' => 'defective', 'reasonDetails' => 'Loose threads', 'refundMethod' => 'original_payment',
        ], $customer)->assertStatus(201)->assertJsonPath('data.status', 'requested')->assertJsonPath('data.statusHistory.0.by', 'Customer')->json('data');

        $this->assertSame($created['id'], $this->getJson('/api/v1/returns', $customer)->assertOk()->json('data.0.id'));
        $this->getJson("/api/v1/returns/{$created['id']}", $customer)->assertOk();
        $this->getJson("/api/v1/returns/{$created['id']}", $this->customerHeaders($this->customer(1)))->assertStatus(404);
        $this->postJson('/api/v1/returns', ['orderId' => 1, 'items' => [['productId' => 6, 'quantity' => 1]], 'reason' => 'other'], $this->customerHeaders($this->customer(2)))->assertStatus(404);
    }

    public function test_direct_payment_refund_and_dashboard(): void
    {
        $admin = $this->adminHeaders();

        $payments = $this->getJson('/api/v1/admin/payments', $admin)->assertOk()->json('data');
        foreach (['id', 'orderId', 'orderNumber', 'amount', 'currency', 'paymentMethod', 'gateway', 'status', 'refundAmount', 'refunds', 'pendingRefund', 'gatewayResponse'] as $key) {
            $this->assertArrayHasKey($key, $payments[0]);
        }
        $this->assertCount(1, $this->getJson('/api/v1/admin/payments?orderId=1', $admin)->json('data'));
        $this->getJson('/api/v1/admin/payments/1', $admin)->assertOk()->assertJsonPath('data.refunds.0.id', 'ref_seed0001');

        $captured = Payment::query()->where('status', 'captured')->whereRaw('amount - refund_amount > 0')->first();
        $remaining = $captured->amount - $captured->refund_amount;
        $this->postJson("/api/v1/admin/payments/{$captured->id}/refund", ['amount' => 999999999, 'reason' => 'Too much'], $admin)
            ->assertStatus(422)->assertJsonPath('message', 'Refund exceeds the remaining '.\App\Support\Money::inr($remaining));

        $result = $this->postJson("/api/v1/admin/payments/{$captured->id}/refund", ['amount' => 100, 'reason' => 'Goodwill'], $admin)->assertOk()->json('data');
        $this->assertSame(100, $result['refundAmount']);
        $this->assertSame('partially_refunded', $result['status']);
        $this->assertSame('Goodwill', $result['refunds'][0]['reason']);
        $this->assertSame('partially_refunded', Order::query()->find($captured->order_id)->payment_status);
        $this->assertSame('Refund issued (₹100)', \App\Models\OrderStatusHistory::query()->where('order_id', $captured->order_id)->orderByDesc('id')->value('action'));
        $this->assertSame('payment_refund', Refund::query()->latest('id')->value('type'));

        $stats = $this->getJson('/api/v1/admin/dashboard/stats', $admin)->assertOk()->json('data');
        $this->assertSame(['totalProducts', 'totalOrders', 'totalRevenue', 'totalUsers', 'pendingOrders', 'pendingReturns', 'lowStockProducts', 'activeCoupons'], array_keys($stats));
        $this->assertSame(6, $stats['totalProducts']);
        $this->assertSame(11, $stats['totalOrders']);
        $this->assertSame(4, $stats['totalUsers']);
        $this->assertSame((int) Order::query()->sum('total'), $stats['totalRevenue']);
    }
}
