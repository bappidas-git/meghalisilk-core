<?php

namespace App\Http\Requests\Storefront;

use App\Http\Requests\ApiFormRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

/**
 * POST /orders — the Checkout payload (guide §14.1). Money/status fields are recomputed server-side.
 */
class PlaceOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'integer'],
            'items.*.variantId' => ['nullable', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shippingAddress' => ['required', 'array'],
            'billingAddress' => ['nullable', 'array'],
            'paymentMethod' => ['required', Rule::in(Order::PAYMENT_METHODS)],
            'couponCode' => ['nullable', 'string', 'max:50'],
            'storeCreditUsed' => ['nullable', 'integer', 'min:0'],
            'shippingAmount' => ['nullable', 'integer', 'min:0'],
            'total' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + self::addressRules('shippingAddress') + self::billingRules();
    }

    private static function billingRules(): array
    {
        $rules = self::addressRules('billingAddress');
        foreach ($rules as $key => $rule) {
            $rules[$key] = array_map(fn ($r) => $r === 'required' ? 'required_with:billingAddress' : $r, $rule);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Your cart is empty.',
            'items.min' => 'Your cart is empty.',
        ];
    }
}
