<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'type', 'amount', 'reason', 'order_id', 'refund_id', 'balance_before', 'balance_after', 'created_at'];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'order_id' => 'integer',
        'refund_id' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
