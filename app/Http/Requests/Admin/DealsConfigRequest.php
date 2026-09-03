<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class DealsConfigRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'hero' => ['required', 'array'],
            'hero.tag' => ['nullable', 'string', 'max:150'],
            'hero.title' => ['required', 'string', 'max:255'],
            'hero.subtitle' => ['nullable', 'string', 'max:1000'],
            'timer' => ['nullable', 'array'],
            'timer.enabled' => ['nullable', 'boolean'],
            'timer.endAt' => ['nullable', 'date'],
            'timer.onExpiry' => ['nullable', Rule::in(['endOfDay', 'hide'])],
            'featuredCouponIds' => ['nullable', 'array'],
            'featuredCouponIds.*' => ['integer'],
            'dealOfTheDayIds' => ['nullable', 'array'],
            'dealOfTheDayIds.*' => ['integer'],
            'featuredProductIds' => ['nullable', 'array'],
            'featuredProductIds.*' => ['integer'],
        ];
    }
}
