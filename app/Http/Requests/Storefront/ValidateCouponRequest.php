<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class ValidateCouponRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'orderAmount' => ['nullable', 'numeric', 'min:0'],
            'userId' => ['nullable'],
        ];
    }
}
