<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;
use App\Models\Lead;
use App\Rules\IndianMobile;
use Illuminate\Validation\Rule;

class ContactLeadRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30', new IndianMobile],
            'orderNumber' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', Rule::in(Lead::CATEGORIES)],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
