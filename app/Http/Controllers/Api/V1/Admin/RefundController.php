<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\RefundResource;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;

class RefundController extends ApiController
{
    public function index(): JsonResponse
    {
        $rows = Refund::query()->with(['order', 'productReturn'])->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(RefundResource::collection($rows));
    }
}
