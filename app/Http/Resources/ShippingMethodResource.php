<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ShippingMethodResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'carrier' => $this->carrier ?? '',
            'description' => $this->description ?? '',
            'rateType' => $this->rate_type,
            'flatRate' => (int) $this->flat_rate,
            'freeAbove' => $this->free_above,
            'estimatedDays' => $this->estimated_days,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
