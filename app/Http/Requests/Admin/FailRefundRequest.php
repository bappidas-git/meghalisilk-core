<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class FailRefundRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:2000']];
    }
}
