<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ReturnResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'returnNumber' => $this->return_number,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order?->order_number,
            'userId' => $this->user_id,
            'items' => $this->items->map(fn ($item) => [
                'productId' => $item->product_id,
                'variantId' => $item->variant_key,
                'variantName' => $item->variant_name,
                'name' => $item->name,
                'sku' => $item->sku ?? '',
                'price' => (int) $item->price,
                'quantity' => (int) $item->quantity,
                'subtotal' => (int) $item->subtotal,
            ])->values()->all(),
            'reason' => $this->reason,
            'reasonDetails' => $this->reason_details ?? '',
            'status' => $this->status,
            'rejectReason' => $this->reject_reason,
            'refundAmount' => (int) $this->refund_amount,
            'refundStatus' => $this->refund_status,
            'refundMethod' => $this->refund_method,
            'deductionAmount' => (int) $this->deduction_amount,
            'restocked' => (bool) $this->restocked,
            'storeCreditCredited' => (bool) $this->store_credit_credited,
            'returnTrackingNumber' => $this->return_tracking_number,
            'returnTrackingUrl' => $this->return_tracking_url,
            'returnCarrier' => $this->return_carrier,
            'pickupScheduledAt' => $this->iso($this->pickup_scheduled_at),
            'images' => array_values($this->images ?? []),
            'notes' => $this->notes ?? '',
            'statusHistory' => $this->statusHistory->map(function ($row) {
                $entry = ['at' => $this->iso($row->at), 'by' => $row->by, 'action' => $row->action];
                if ($row->note !== null && $row->note !== '') {
                    $entry['note'] = $row->note;
                }

                return $entry;
            })->values()->all(),
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
