<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Admin console authentication (guide §13.14).
 */
class AdminAuthController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $admin = Admin::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['email']))])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return response()->json(['message' => 'Invalid admin credentials'], 401);
        }
        if (! $admin->is_active) {
            return response()->json(['message' => EnsureAccountActive::MESSAGE], 403);
        }

        $days = config('store.token_ttl_days');
        $token = $admin->createToken('admin', [Admin::TOKEN_ABILITY], $days ? now()->addDays((int) $days) : null);

        return $this->respond([
            'token' => $token->plainTextToken,
            'admin' => AdminResource::make($admin)->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->respond(null);
    }
}
