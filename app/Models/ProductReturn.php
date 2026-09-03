<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Table `returns` ("Return" is reserved-ish, guide §35).
 */
class ProductReturn extends BaseModel
{
    public const REASONS = ['defective', 'wrong_item', 'not_as_described', 'size_issue', 'changed_mind', 'other'];

    public const STATUSES = ['requested', 'approved', 'pickup_scheduled', 'in_transit', 'received', 'refunded', 'rejected'];

    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected'],
        'approved' => ['pickup_scheduled', 'received'],
        'pickup_scheduled' => ['in_transit', 'received'],
        'in_transit' => ['received'],
        'received' => ['refunded'],
        'refunded' => [],
        'rejected' => [],
    ];

    public const REFUND_METHODS = ['original_payment', 'store_credit', 'bank_transfer', 'upi'];

    protected $table = 'returns';

    protected $fillable = [
        'return_number', 'order_id', 'user_id', 'reason', 'reason_details', 'status', 'reject_reason', 'refund_amount',
        'refund_status', 'refund_method', 'deduction_amount', 'restocked', 'store_credit_credited',
        'return_tracking_number', 'return_tracking_url', 'return_carrier', 'pickup_scheduled_at', 'images', 'notes',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'user_id' => 'integer',
        'refund_amount' => 'integer',
        'deduction_amount' => 'integer',
        'restocked' => 'boolean',
        'store_credit_credited' => 'boolean',
        'pickup_scheduled_at' => 'datetime',
        'images' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id')->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReturnStatusHistory::class, 'return_id')->orderBy('at')->orderBy('id');
    }

    public function canTransitionTo(string $status): bool
    {
        return $status === $this->status || in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
