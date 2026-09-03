<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class ShippingMethodRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'carrier' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'rateType' => ['required', Rule::in(['flat', 'free'])],
            'flatRate' => ['nullable', 'integer', 'min:0'],
            'freeAbove' => ['nullable', 'integer', 'min:0'],
            'estimatedDays' => ['nullable', 'string', 'max:20'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
