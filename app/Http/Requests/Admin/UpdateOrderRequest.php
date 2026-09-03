<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

/**
 * PATCH /admin/orders/{id} (guide §14.5).
 */
class UpdateOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $address = self::addressRules('shippingAddress', requireLastName: false, requirePhone: false);
        foreach ($address as $key => $rule) {
            $address[$key] = array_map(fn ($r) => $r === 'required' ? 'required_with:shippingAddress' : $r, $rule);
        }

        return [
            'fulfillmentStatus' => ['sometimes', 'nullable', Rule::in(Order::FULFILLMENT_STATUSES)],
            'shippingStatus' => ['sometimes', 'nullable', Rule::in(Order::SHIPPING_STATUSES)],
            'paymentStatus' => ['sometimes', 'nullable', Rule::in(Order::PAYMENT_STATUSES)],
            'trackingNumber' => ['nullable', 'string', 'max:100'],
            'trackingUrl' => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'deliveredAt' => ['nullable', 'date'],
            'shippingAddress' => ['sometimes', 'array'],
            'event' => ['nullable', 'array'],
            'event.action' => ['required_with:event', 'string', 'max:255'],
            'event.note' => ['nullable', 'string', 'max:2000'],
        ] + $address;
    }
}
