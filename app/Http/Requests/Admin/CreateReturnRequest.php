<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\ProductReturn;
use Illuminate\Validation\Rule;

/**
 * POST /admin/returns (guide §14.6). refundAmount / orderNumber / userId are hints the server recomputes.
 */
class CreateReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'orderId' => ['required', 'integer', Rule::exists('orders', 'id')],
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
