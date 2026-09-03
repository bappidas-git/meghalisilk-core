<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductReturn;
use App\Models\Refund;
use Illuminate\Support\Str;

/**
 * Server-owned business numbers (guide §24.3, §11.17, §11.18).
 */
class NumberGenerator
{
    public function orderNumber(): string
    {
        return $this->daily('ORD', Order::class, 'order_number');
    }

    public function returnNumber(): string
    {
        return $this->daily('RET', ProductReturn::class, 'return_number');
    }

    public function refundNumber(): string
    {
        do {
            $number = 'REF-'.now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (Refund::query()->where('refund_number', $number)->exists());

        return $number;
    }

    /** payments[].refunds[].id — "ref_<base36>" */
    public function refKey(): string
    {
        return 'ref_'.base_convert((string) (int) (microtime(true) * 1000), 10, 36).base_convert((string) random_int(1000, 46655), 10, 36);
    }

    /** Placeholder transaction id for manually confirmed payments. */
    public function manualTransactionId(): string
    {
        return 'manual_'.Str::upper(base_convert((string) (int) (microtime(true) * 1000), 10, 36));
    }

    private function daily(string $prefix, string $model, string $column): string
    {
        $date = now()->format('Ymd');
        $base = $prefix.'-'.$date.'-';

        $last = $model::query()
            ->where($column, 'like', $base.'%')
            ->orderByDesc($column)
            ->value($column);

        $seq = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        do {
            $number = $base.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while ($model::query()->where($column, $number)->exists());

        return $number;
    }
}
