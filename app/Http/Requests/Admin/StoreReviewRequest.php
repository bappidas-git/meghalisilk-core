<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Review;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'productId' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'userName' => ['required', 'string', 'max:150'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'isVerifiedPurchase' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(Review::STATUSES)],
        ];
    }
}
