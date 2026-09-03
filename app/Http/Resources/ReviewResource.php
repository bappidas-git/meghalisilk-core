<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ReviewResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->product_id,
            'userId' => $this->user_id,
            'orderId' => $this->order_id,
            'orderNumber' => $this->order?->order_number,
            'userName' => $this->user_name,
            'rating' => (int) $this->rating,
            'title' => $this->title ?? '',
            'body' => $this->body ?? '',
            'status' => $this->status,
            'isVerifiedPurchase' => (bool) $this->is_verified_purchase,
            'helpfulCount' => (int) $this->helpful_count,
            'source' => $this->when($this->source !== null, $this->source),
            'photos' => $this->when($this->photos !== null, array_values($this->photos ?? [])),
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
