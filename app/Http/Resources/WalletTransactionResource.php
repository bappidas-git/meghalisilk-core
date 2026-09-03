<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class WalletTransactionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'type' => $this->type,
            'amount' => (int) $this->amount,
            'reason' => $this->reason,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order?->order_number,
            'refundId' => $this->refund_id,
            'refundNumber' => $this->refund?->refund_number,
            'balanceBefore' => (int) $this->balance_before,
            'balanceAfter' => (int) $this->balance_after,
            'createdAt' => $this->iso($this->created_at),
        ];
    }
}
