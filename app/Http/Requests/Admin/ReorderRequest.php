<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class ReorderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'distinct'],
        ];
    }
}
