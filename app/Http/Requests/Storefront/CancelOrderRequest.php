<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class CancelOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
