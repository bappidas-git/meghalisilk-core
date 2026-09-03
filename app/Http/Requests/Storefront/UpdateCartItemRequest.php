<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class UpdateCartItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
        ];
    }
}
