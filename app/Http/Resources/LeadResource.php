<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LeadResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?? '',
            'orderNumber' => $this->order_number ?? '',
            'category' => $this->category,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'notes' => $this->notes ?? '',
            'createdAt' => $this->iso($this->created_at),
            'updatedAt' => $this->iso($this->updated_at),
        ];
    }
}
