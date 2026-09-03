<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Admin customers (guide §13.24). Only isActive is writable.
 */
class UserController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->respond(UserResource::collection(User::query()->with('addresses')->orderBy('id')->get()));
    }

    public function show(int $id): JsonResponse
    {
        return $this->respond(UserResource::make(User::query()->with('addresses')->findOrFail($id)));
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $isActive = (bool) $request->validated('isActive');

        $user->forceFill(['is_active' => $isActive])->save();
        if (! $isActive) {
            $user->tokens()->delete();
        }

        return $this->respond(UserResource::make($user->fresh('addresses')));
    }
}
