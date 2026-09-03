<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;
use App\Rules\IndianMobile;

/**
 * PUT /auth/user — profile fields or the whole address book (guide §13.1).
 */
class UpdateProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'firstName' => ['sometimes', 'required', 'string', 'max:100'],
            'lastName' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', new IndianMobile],
            'addresses' => ['sometimes', 'array'],
        ] + self::addressRules('addresses.*');
    }
}
