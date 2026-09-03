<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Services\DashboardStats;
use Illuminate\Http\JsonResponse;

class DashboardController extends ApiController
{
    public function stats(DashboardStats $stats): JsonResponse
    {
        return $this->respond($stats->compute());
    }
}
