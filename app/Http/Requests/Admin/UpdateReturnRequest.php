<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use App\Models\ProductReturn;
use Illuminate\Validation\Rule;

/**
 * PATCH /admin/returns/{id} (guide §14.7).
 */
class UpdateReturnRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(ProductReturn::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'rejectReason' => ['nullable', 'string', 'max:2000', 'required_if:status,rejected'],
            'refundStatus' => ['sometimes', 'nullable', Rule::in(['pending', 'processed'])],
            'deductionAmount' => ['nullable', 'integer', 'min:0'],
            'refundMethod' => ['sometimes', 'nullable', Rule::in(ProductReturn::REFUND_METHODS)],
            'returnTrackingNumber' => ['nullable', 'string', 'max:100'],
            'returnTrackingUrl' => ['nullable', 'string', 'max:1000', 'url:http,https'],
            'returnCarrier' => ['nullable', 'string', 'max:100'],
            'pickupScheduledAt' => ['nullable', 'date'],
            'event' => ['nullable', 'array'],
            'event.action' => ['required_with:event', 'string', 'max:255'],
            'event.note' => ['nullable', 'string', 'max:2000'],
            'restock' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['rejectReason.required_if' => 'A reason is required to reject a return.'];
    }
}
