<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authentication / scope is enforced by route middleware
    }

    /** Address rules shared by checkout, profile and admin address edits (guide §16). */
    protected static function addressRules(string $prefix, bool $requireLastName = true, bool $requirePhone = true): array
    {
        return [
            "$prefix.id" => ['nullable'],
            "$prefix.label" => ['nullable', 'string', 'max:50'],
            "$prefix.firstName" => ['required', 'string', 'max:100'],
            "$prefix.lastName" => [$requireLastName ? 'required' : 'nullable', 'string', 'max:100'],
            "$prefix.phone" => [$requirePhone ? 'required' : 'nullable', 'string', 'max:30', new \App\Rules\IndianMobile],
            "$prefix.addressLine1" => ['required', 'string', 'max:255'],
            "$prefix.addressLine2" => ['nullable', 'string', 'max:255'],
            "$prefix.city" => ['required', 'string', 'max:100'],
            "$prefix.state" => ['required', 'string', 'max:100'],
            "$prefix.postalCode" => ['required', 'string', 'max:20'],
            "$prefix.country" => ['nullable', 'string', 'max:100'],
            "$prefix.isDefault" => ['nullable', 'boolean'],
        ];
    }
}
