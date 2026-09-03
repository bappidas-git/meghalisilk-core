<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class WishlistItemResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'productId' => $this->product_id,
            'createdAt' => $this->iso($this->created_at),
            'product' => $this->product ? ProductResource::make($this->product)->resolve($request) : null,
        ];
    }
}
