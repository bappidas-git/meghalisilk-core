<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orderNumber' => $this->order_number,
            'userId' => $this->user_id,
            'items' => $this->items->map(fn ($item) => [
                'productId' => $item->product_id,
                'variantId' => $item->variant_key,
                'variantName' => $item->variant_name,
                'name' => $item->name,
                'image' => $item->image,
                'sku' => $item->sku ?? '',
                'price' => (int) $item->price,
                'quantity' => (int) $item->quantity,
                'subtotal' => (int) $item->subtotal,
            ])->values()->all(),
            'shippingAddress' => $this->object($this->shipping_address),
            'billingAddress' => $this->object($this->billing_address),
            'subtotal' => (int) $this->subtotal,
            'discountAmount' => (int) $this->discount_amount,
            'couponCode' => $this->coupon_code,
            'couponRestored' => (bool) $this->coupon_restored,
            'shippingAmount' => (int) $this->shipping_amount,
            'taxAmount' => (int) $this->tax_amount,
            'codFee' => (int) $this->cod_fee,
            'total' => (int) $this->total,
            'storeCreditUsed' => (int) $this->store_credit_used,
            'storeCreditReturned' => (bool) $this->store_credit_returned,
            'amountPayable' => (int) $this->amount_payable,
            'paymentMethod' => $this->payment_method,
            'paymentStatus' => $this->payment_status,
            'fulfillmentStatus' => $this->fulfillment_status,
            'shippingStatus' => $this->shipping_status,
            'trackingNumber' => $this->tracking_number,
            'trackingUrl' => $this->tracking_url,
            'shiprocketOrderId' => $this->shiprocket_order_id,
            'notes' => $this->notes ?? '',
            'cancelReason' => $this->cancel_reason,
            'cancelledAt' => $this->iso($this->cancelled_at),
            'deliveredAt' => $this->iso($this->delivered_at),
            'refundStatus' => $this->refund_status,
            'refundMethod' => $this->refund_method,
            'refundedAmount' => (int) $this->refunded_amount,
            'refundCompletedAt' => $this->iso($this->refund_completed_at),
            'pendingRefund' => $this->pending_refund,
            'recall' => $this->recall,
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
