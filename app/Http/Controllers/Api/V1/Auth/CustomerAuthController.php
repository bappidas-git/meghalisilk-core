<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserAddressSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Customer authentication & profile (guide §13.1).
 */
class CustomerAuthController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }
        if (! $user->is_active) {
            return response()->json(['message' => EnsureAccountActive::MESSAGE], 403);
        }

        $days = ! empty($data['remember']) ? config('store.token_ttl_remember_days') : config('store.token_ttl_days');
        $token = $user->createToken('customer', [User::TOKEN_ABILITY], $days ? now()->addDays((int) $days) : null);

        return $this->respond([
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user->load('addresses'))->resolve($request),
        ]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'email' => $data['email'],
            'password' => $data['password'],
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'phone' => $data['phone'] ?? null,
            'avatar' => null,
            'is_active' => true,
            'store_credit' => 0,
        ]);

        return $this->respond(UserResource::make($user->load('addresses')), 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->respond(null);
    }

    public function user(Request $request): JsonResponse
    {
        return $this->respond(UserResource::make($request->user()->load('addresses')));
    }

    public function updateUser(UpdateProfileRequest $request, UserAddressSync $addresses): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $profile = [];
        if (array_key_exists('firstName', $data)) {
            $profile['first_name'] = $data['firstName'];
        }
        if (array_key_exists('lastName', $data)) {
            $profile['last_name'] = $data['lastName'];
        }
        if (array_key_exists('phone', $data)) {
            $profile['phone'] = $data['phone'] === '' ? null : $data['phone'];
        }
        if ($profile) {
            $user->fill($profile)->save();
        }

        if (array_key_exists('addresses', $data)) {
            $addresses->sync($user, $data['addresses'] ?? []);
        }

        return $this->respond(UserResource::make($user->fresh('addresses')));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Current password is incorrect']]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        // Revoke every other session; the current token keeps working.
        $current = $user->currentAccessToken();
        $user->tokens()->when($current, fn ($q) => $q->where('id', '!=', $current->id))->delete();

        return $this->respond(['success' => true]);
    }
}
