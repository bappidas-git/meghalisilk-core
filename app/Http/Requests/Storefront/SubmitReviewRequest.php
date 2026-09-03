<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class SubmitReviewRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:1000'],
            'orderId' => ['nullable', 'integer'],
        ];
    }
}
