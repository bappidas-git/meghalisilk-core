<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;

class SettingsController extends ApiController
{
    /** Only the public sections: store, payment, social (guide §13.12). */
    public function show(SettingsRepository $settings): JsonResponse
    {
        return $this->respond($settings->publicSettings());
    }
}
