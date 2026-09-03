<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Http\JsonResponse;

/**
 * Shiprocket proxy endpoints are defined by the service layer but no screen calls them.
 * Guide §13.21 / §23.2: respond 501 until the integration is commissioned.
 */
class ShiprocketController extends ApiController
{
    public function createOrder(): JsonResponse
    {
        return $this->notEnabled();
    }

    public function track(string $trackingNumber): JsonResponse
    {
        return $this->notEnabled();
    }

    private function notEnabled(): JsonResponse
    {
        return response()->json(['message' => 'Shiprocket integration is not enabled.'], 501);
    }
}
