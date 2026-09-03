<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RefundResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'refundNumber' => $this->refund_number,
            'type' => $this->type,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order?->order_number,
            'returnId' => $this->return_id,
            'returnNumber' => $this->productReturn?->return_number,
            'paymentId' => $this->payment_id,
            'amount' => (int) $this->amount,
            'method' => $this->method,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'status' => $this->status,
            'couponRestored' => (bool) $this->coupon_restored,
            'initiatedAt' => $this->iso($this->initiated_at),
            'settledAt' => $this->iso($this->settled_at),
            'by' => $this->by,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
