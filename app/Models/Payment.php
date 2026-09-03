<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends BaseModel
{
    protected $fillable = [
        'order_id', 'user_id', 'amount', 'currency', 'payment_method', 'gateway', 'transaction_id',
        'gateway_order_id', 'status', 'gateway_response', 'refund_amount', 'refund_reason', 'pending_refund',
        'store_credit_applied',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'amount' => 'integer',
        'gateway_response' => 'array',
        'refund_amount' => 'integer',
        'pending_refund' => 'array',
        'store_credit_applied' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refundEntries(): HasMany
    {
        return $this->hasMany(PaymentRefund::class)->orderBy('at')->orderBy('id');
    }

    public function remaining(): int
    {
        return max(0, $this->amount - $this->refund_amount);
    }
}
