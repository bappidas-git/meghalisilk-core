<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserAddress;

class AuthTest extends ApiTestCase
{
    public function test_register_returns_safe_user_without_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'firstName' => 'Anjali', 'lastName' => 'Baruah', 'email' => 'anjali.baruah@example.com',
            'phone' => '+919876543210', 'password' => 'Silk@2026', 'password_confirmation' => 'Silk@2026',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'anjali.baruah@example.com')
            ->assertJsonPath('data.firstName', 'Anjali')
            ->assertJsonPath('data.storeCredit', 0)
            ->assertJsonPath('data.addresses', [])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.token');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $response->json('data.createdAt'));
    }

    public function test_register_rejects_duplicate_email_with_storefront_message(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'firstName' => 'A', 'lastName' => 'B', 'email' => 'user@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'An account with this email already exists. Please log in instead.')
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_login_returns_token_and_user(): void
    {
        $response = $this->postJson('/api/v1/auth/login', ['email' => 'user@example.com', 'password' => 'password123', 'remember' => true]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', 1)
            ->assertJsonMissingPath('data.user.password');

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/auth/user')
            ->assertOk()->assertJsonPath('data.email', 'user@example.com')->assertJsonPath('data.addresses.0.city', 'Mumbai');
    }

    public function test_login_failures(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'user@example.com', 'password' => 'wrong'])
            ->assertStatus(401)->assertJsonPath('message', 'Invalid email or password');

        User::query()->whereKey(1)->update(['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['email' => 'user@example.com', 'password' => 'password123'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This account has been deactivated. Please contact support if you think this is a mistake.');
    }

    public function test_wrong_scope_tokens_get_401(): void
    {
        $this->getJson('/api/v1/admin/dashboard/stats', $this->customerHeaders())->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
        $this->getJson('/api/v1/auth/user', $this->adminHeaders())->assertStatus(401);
        $this->getJson('/api/v1/auth/user')->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_deactivated_user_is_rejected_on_every_request(): void
    {
        $headers = $this->customerHeaders();
        User::query()->whereKey(3)->update(['is_active' => false]);

        $this->getJson('/api/v1/auth/user', $headers)->assertStatus(401);
    }

    public function test_update_profile_and_address_book_reconciliation(): void
    {
        $headers = $this->customerHeaders();

        $this->putJson('/api/v1/auth/user', ['firstName' => 'Bappi', 'lastName' => 'Das', 'phone' => '9707112233'], $headers)
            ->assertOk()->assertJsonPath('data.phone', '9707112233');

        // email / storeCredit are never client-writable
        $this->putJson('/api/v1/auth/user', ['firstName' => 'X', 'lastName' => 'Y', 'email' => 'new@example.com', 'storeCredit' => 99999], $headers)
            ->assertOk()->assertJsonPath('data.email', 'mail4bappidas@gmail.com')->assertJsonPath('data.storeCredit', 3918);

        $existingId = UserAddress::query()->where('user_id', 3)->value('id');
        $response = $this->putJson('/api/v1/auth/user', ['addresses' => [
            ['id' => $existingId, 'label' => 'Home', 'firstName' => 'Bappi', 'lastName' => 'Das', 'phone' => '+919707112233',
                'addressLine1' => 'Updated line', 'addressLine2' => '', 'city' => 'Howly', 'state' => 'Assam', 'postalCode' => '781316', 'country' => 'India', 'isDefault' => false],
            ['id' => 'm0x8kz3a9f1', 'label' => 'Work', 'firstName' => 'Bappi', 'lastName' => 'Das', 'phone' => '+91 9876543210',
                'addressLine1' => '123 Main Street', 'city' => 'Guwahati', 'state' => 'Assam', 'postalCode' => '781001', 'country' => 'India', 'isDefault' => true],
        ]], $headers);

        $response->assertOk();
        $addresses = $response->json('data.addresses');
        $this->assertCount(2, $addresses);
        $this->assertSame($existingId, $addresses[0]['id']);
        $this->assertSame('Updated line', $addresses[0]['addressLine1']);
        $this->assertIsInt($addresses[1]['id']);
        $this->assertTrue($addresses[1]['isDefault']);
        $this->assertFalse($addresses[0]['isDefault']);

        // removing every row deletes them
        $this->putJson('/api/v1/auth/user', ['addresses' => []], $headers)->assertOk()->assertJsonPath('data.addresses', []);
        $this->assertSame(0, UserAddress::query()->where('user_id', 3)->count());
    }

    public function test_change_password(): void
    {
        $headers = $this->customerHeaders();

        $this->putJson('/api/v1/auth/password', ['current_password' => 'nope', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'], $headers)
            ->assertStatus(422)->assertJsonPath('message', 'Current password is incorrect');

        $this->putJson('/api/v1/auth/password', ['current_password' => 'Bappi@12345', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'], $headers)
            ->assertOk()->assertJsonPath('data.success', true);

        $this->postJson('/api/v1/auth/login', ['email' => 'mail4bappidas@gmail.com', 'password' => 'NewPass123'])->assertOk();
    }

    public function test_logout_revokes_token(): void
    {
        $headers = $this->customerHeaders();
        $this->postJson('/api/v1/auth/logout', [], $headers)->assertOk()->assertJsonPath('data', null);
        $this->getJson('/api/v1/auth/user', $headers)->assertStatus(401);
    }

    public function test_admin_login_and_logout(): void
    {
        $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@store.com', 'password' => 'nope'])
            ->assertStatus(401)->assertJsonStructure(['message']);

        $response = $this->postJson('/api/v1/admin/auth/login', ['email' => 'admin@store.com', 'password' => 'admin123']);
        $response->assertOk()->assertJsonPath('data.admin.role', 'super_admin')->assertJsonMissingPath('data.admin.password');

        $headers = ['Authorization' => 'Bearer '.$response->json('data.token')];
        $this->getJson('/api/v1/admin/dashboard/stats', $headers)->assertOk();
        $this->postJson('/api/v1/admin/auth/logout', [], $headers)->assertOk();
        $this->getJson('/api/v1/admin/dashboard/stats', $headers)->assertStatus(401);
    }
}
