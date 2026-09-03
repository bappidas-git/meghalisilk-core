<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiController extends Controller
{
    /**
     * Success envelope: { "success": true, "data": ... } (guide §6 / §15).
     */
    protected function respond(mixed $data = null, int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json(['success' => true, 'data' => $data], $status);
    }

    /** Audit actor name for the current request. */
    protected function actor(): string
    {
        return app(AuditTrail::class)->actor();
    }

    protected function admin(): Admin
    {
        return request()->user();
    }
}
