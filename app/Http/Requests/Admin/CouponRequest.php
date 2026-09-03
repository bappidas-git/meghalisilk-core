<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Coupon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CouponRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id ?? $this->route('id');

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($couponId)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['required', 'integer', 'gt:0'],
            'minOrderAmount' => ['nullable', 'integer', 'min:0'],
            'maxDiscount' => ['nullable', 'integer', 'min:1'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'perUserLimit' => ['nullable', 'integer', 'min:1'],
            'isActive' => ['nullable', 'boolean'],
            'expiresAt' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Coupon code "'.Coupon::normalizeCode($this->input('code')).'" already exists'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Coupon::normalizeCode($this->input('code'))]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->input('type') === 'percentage' && (int) $this->input('value') > 100) {
                $v->errors()->add('value', 'A percentage discount cannot exceed 100.');
            }
        });
    }
}
