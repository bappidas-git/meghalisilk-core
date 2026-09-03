<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);
        $user = $this->user;
        $data['customerEmail'] = $user?->email;
        $data['customerName'] = $user ? trim($user->first_name.' '.$user->last_name) : null;

        return $data;
    }
}
