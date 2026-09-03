<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;

class NewsletterLeadRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:191']];
    }
}
