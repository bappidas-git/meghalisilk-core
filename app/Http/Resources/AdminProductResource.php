<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminProductResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $data['costPrice'] = (int) $this->cost_price;

        return $data;
    }
}
