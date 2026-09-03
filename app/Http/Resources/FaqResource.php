<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FaqResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'answer' => $this->answer,
            'placements' => array_values($this->placements ?? []),
            'productIds' => $this->products->pluck('id')->values()->all(),
            'isActive' => (bool) $this->is_active,
            'sortOrder' => (int) $this->sort_order,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
