<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends BaseModel
{
    public const TYPES = ['order_cancellation', 'recall_refund', 'order_refund', 'return_refund', 'payment_refund'];

    protected $fillable = [
        'refund_number', 'type', 'order_id', 'return_id', 'payment_id', 'amount', 'method', 'reason', 'reference',
        'status', 'coupon_restored', 'initiated_at', 'settled_at', 'by',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'return_id' => 'integer',
        'payment_id' => 'integer',
        'amount' => 'integer',
        'coupon_restored' => 'boolean',
        'initiated_at' => 'datetime',
        'settled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
