<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class UpdateUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['isActive' => ['required', 'boolean']];
    }
}
