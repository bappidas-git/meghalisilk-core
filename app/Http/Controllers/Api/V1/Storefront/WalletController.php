<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\WalletTransactionResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function balance(Request $request, WalletService $wallet): JsonResponse
    {
        return $this->respond(['balance' => $wallet->balance($request->user())]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $rows = $request->user()->walletTransactions()->with(['order', 'refund'])
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->respond(WalletTransactionResource::collection($rows));
    }
}
