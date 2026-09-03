<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProductReturn;
use App\Models\ReturnStatusHistory;
use App\Models\User;

/**
 * Status-history rows and the "by" actor name (guide §29).
 */
class AuditTrail
{
    public function actor(): string
    {
        $user = auth()->user();

        if ($user instanceof Admin) {
            return $user->displayName();
        }
        if ($user instanceof User) {
            return 'Customer';
        }

        return 'System';
    }

    public function order(Order $order, string $action, ?string $note = null, ?string $by = null): OrderStatusHistory
    {
        return $order->statusHistory()->create([
            'at' => now(),
            'by' => $by ?? $this->actor(),
            'action' => $action,
            'note' => ($note === null || $note === '') ? null : $note,
        ]);
    }

    public function return(ProductReturn $return, string $action, ?string $note = null, ?string $by = null): ReturnStatusHistory
    {
        return $return->statusHistory()->create([
            'at' => now(),
            'by' => $by ?? $this->actor(),
            'action' => $action,
            'note' => ($note === null || $note === '') ? null : $note,
        ]);
    }
}
