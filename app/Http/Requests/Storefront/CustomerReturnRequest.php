<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;
use App\Models\ProductReturn;
use Illuminate\Validation\Rule;

class CustomerReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'orderId' => ['required', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.variantId' => ['nullable', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(ProductReturn::REASONS)],
            'reasonDetails' => ['nullable', 'string', 'max:2000'],
            'refundMethod' => ['nullable', Rule::in(ProductReturn::REFUND_METHODS)],
        ];
    }
}
