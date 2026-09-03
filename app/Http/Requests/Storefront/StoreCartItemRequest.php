<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class StoreCartItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'productId' => ['required', 'integer'],
            'variantId' => ['nullable', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
