<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AddressResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone,
            'addressLine1' => $this->address_line1,
            'addressLine2' => $this->address_line2 ?? '',
            'city' => $this->city,
            'state' => $this->state,
            'postalCode' => $this->postal_code,
            'country' => $this->country,
            'isDefault' => (bool) $this->is_default,
        ];
    }
}
