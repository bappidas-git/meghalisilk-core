<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class StoreWishlistItemRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['productId' => ['required', 'integer']];
    }
}
