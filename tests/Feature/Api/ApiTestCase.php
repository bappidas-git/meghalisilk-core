<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\DbJsonImportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every API test runs against the imported db.json seed (guide §37) on an in-memory SQLite database.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DbJsonImportSeeder::class);
    }

    /**
     * The test harness keeps the resolved guard user across requests in one test; forget it so
     * every request authenticates strictly from its own Authorization header, like a real client.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function customer(int $id = 3): User
    {
        return User::query()->findOrFail($id);
    }

    protected function admin(): Admin
    {
        return Admin::query()->firstOrFail();
    }

    protected function customerHeaders(?User $user = null): array
    {
        $user ??= $this->customer();
        $token = $user->createToken('customer', [User::TOKEN_ABILITY])->plainTextToken;

        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    protected function adminHeaders(): array
    {
        $token = $this->admin()->createToken('admin', [Admin::TOKEN_ABILITY])->plainTextToken;

        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    /** The checkout payload for a single line of product 1 (v1 = ₹32,500), COD by default. */
    protected function orderPayload(array $overrides = []): array
    {
        $address = [
            'id' => 1, 'label' => 'Home', 'firstName' => 'Bappi', 'lastName' => 'Das', 'phone' => '+919707112233',
            'addressLine1' => 'Moutupuri, Barpeta', 'addressLine2' => 'Near BH College', 'city' => 'Howly',
            'state' => 'Assam', 'postalCode' => '781316', 'country' => 'India', 'isDefault' => true,
        ];

        return array_replace([
            'items' => [[
                'productId' => 1, 'variantId' => 'v1', 'name' => 'Sualkuchi Muga Mekhela Chador — Natural Gold - Natural Gold',
                'image' => 'https://placehold.co/600x800', 'sku' => '', 'price' => 32500, 'quantity' => 1, 'subtotal' => 32500,
            ]],
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'subtotal' => 32500, 'discountAmount' => 0, 'couponCode' => null, 'shippingAmount' => 0, 'taxAmount' => 1625,
            'codFee' => 0, 'total' => 34125, 'storeCreditUsed' => 0, 'amountPayable' => 34125,
            'paymentMethod' => 'cod', 'paymentStatus' => 'pending', 'fulfillmentStatus' => 'unfulfilled',
            'shippingStatus' => 'pending', 'trackingNumber' => null, 'shiprocketOrderId' => null, 'notes' => '',
            'userId' => 3, 'orderNumber' => 'ORD-MQDVIQCV-30A9',
            'createdAt' => '2026-09-03T10:00:00.000Z', 'updatedAt' => '2026-09-03T10:00:00.000Z',
        ], $overrides);
    }
}
