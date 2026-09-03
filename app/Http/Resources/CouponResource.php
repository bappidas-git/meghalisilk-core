<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CouponResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description ?? '',
            'type' => $this->type,
            'value' => (int) $this->value,
            'minOrderAmount' => (int) $this->min_order_amount,
            'maxDiscount' => $this->max_discount,
            'usageLimit' => $this->usage_limit,
            'usedCount' => (int) $this->used_count,
            'perUserLimit' => $this->per_user_limit,
            'isActive' => (bool) $this->is_active,
            'expiresAt' => $this->iso($this->expires_at),
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
