<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PaymentResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order?->order_number,
            'userId' => $this->user_id,
            'amount' => (int) $this->amount,
            'currency' => $this->currency,
            'paymentMethod' => $this->payment_method,
            'gateway' => $this->gateway,
            'transactionId' => $this->transaction_id,
            'gatewayOrderId' => $this->gateway_order_id,
            'status' => $this->status,
            'gatewayResponse' => $this->object($this->gateway_response),
            'refundAmount' => (int) $this->refund_amount,
            'refundReason' => $this->refund_reason,
            'refunds' => $this->refundEntries->map(fn ($row) => [
                'id' => $row->ref_key,
                'amount' => (int) $row->amount,
                'reason' => $row->reason,
                'at' => $this->iso($row->at),
                'by' => $row->by,
            ])->values()->all(),
            'pendingRefund' => $this->pending_refund,
            'storeCreditApplied' => (int) $this->store_credit_applied,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
