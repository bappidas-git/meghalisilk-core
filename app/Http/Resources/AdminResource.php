<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'role' => $this->role,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->iso($this->created_at),
        ];
    }
}
