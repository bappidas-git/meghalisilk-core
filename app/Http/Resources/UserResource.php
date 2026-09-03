<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'phone' => $this->phone ?? '',
            'avatar' => $this->avatar,
            'addresses' => AddressResource::collection($this->addresses)->resolve($request),
            'isActive' => (bool) $this->is_active,
            'storeCredit' => (int) $this->store_credit,
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
