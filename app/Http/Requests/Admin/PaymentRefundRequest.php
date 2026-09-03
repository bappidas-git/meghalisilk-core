<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class PaymentRefundRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
