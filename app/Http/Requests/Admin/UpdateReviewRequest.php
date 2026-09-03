<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Review;
use Illuminate\Validation\Rule;

class UpdateReviewRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(Review::STATUSES)],
            'rating' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'userName' => ['sometimes', 'required', 'string', 'max:150'],
            'isVerifiedPurchase' => ['sometimes', 'boolean'],
        ];
    }
}
