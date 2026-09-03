<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Faq;
use Illuminate\Validation\Rule;

class FaqRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'placements' => ['required', 'array', 'min:1'],
            'placements.*' => [Rule::in(Faq::PLACEMENTS)],
            'productIds' => ['nullable', 'array'],
            'productIds.*' => ['integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'isActive' => ['nullable', 'boolean'],
            'sortOrder' => ['nullable', 'integer'],
        ];
    }
}
