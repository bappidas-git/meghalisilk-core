<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class Coupon extends BaseModel
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_order_amount', 'max_discount', 'usage_limit', 'used_count',
        'per_user_limit', 'is_active', 'expires_at',
    ];

    protected $casts = [
        'value' => 'integer',
        'min_order_amount' => 'integer',
        'max_discount' => 'integer',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'per_user_limit' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function normalizeCode(?string $code): string
    {
        return mb_strtoupper(trim((string) $code));
    }

    public function scopeCode(Builder $query, ?string $code): Builder
    {
        return $query->where('code', self::normalizeCode($code));
    }

    public function scopeActiveUnexpired(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    /** Discount for a subtotal (guide §24.2). */
    public function discountFor(int $subtotal): int
    {
        $raw = $this->type === 'percentage' ? (int) round($subtotal * $this->value / 100) : $this->value;
        $capped = $this->max_discount ? min($raw, $this->max_discount) : $raw;

        return max(0, min($capped, $subtotal));
    }
}
