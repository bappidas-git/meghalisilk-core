<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\SettingsRepository;
use Illuminate\Http\JsonResponse;

class DealsController extends ApiController
{
    public function config(SettingsRepository $settings): JsonResponse
    {
        return $this->respond($settings->dealsConfig());
    }
}
