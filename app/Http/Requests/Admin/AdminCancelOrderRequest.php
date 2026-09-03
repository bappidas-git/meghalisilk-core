<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

class AdminCancelOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'restock' => ['nullable', 'boolean'],
            'refund' => ['nullable', 'array'],
            'refund.method' => ['required_with:refund', Rule::in(Order::REFUND_METHODS)],
            'refund.amount' => ['nullable', 'integer', 'min:1'],
            'refund.reference' => ['nullable', 'string', 'max:100'],
            'voidPayment' => ['nullable', 'boolean'],
            'recall' => ['nullable', 'array'],
            'recall.trackingNumber' => ['nullable', 'string', 'max:100'],
            'recall.trackingUrl' => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'recall.carrier' => ['nullable', 'string', 'max:100'],
        ];
    }
}
