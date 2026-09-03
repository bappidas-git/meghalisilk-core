<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends BaseModel
{
    public const PAYMENT_METHODS = ['card', 'upi', 'net_banking', 'wallet', 'cod', 'store_credit'];

    public const ONLINE_METHODS = ['card', 'upi', 'net_banking', 'wallet'];

    public const PAYMENT_STATUSES = ['pending', 'paid', 'partially_paid', 'partially_refunded', 'refunded', 'failed', 'voided'];

    public const FULFILLMENT_STATUSES = ['unfulfilled', 'partially_fulfilled', 'fulfilled', 'returned', 'cancelled'];

    public const SHIPPING_STATUSES = ['pending', 'shipped', 'delivered', 'recalled'];

    public const REFUND_METHODS = ['original_payment', 'bank_transfer', 'upi', 'store_credit'];

    public const DEFAULT_RELATIONS = ['items', 'statusHistory', 'user'];

    protected $fillable = [
        'order_number', 'user_id', 'coupon_id', 'coupon_code', 'coupon_restored', 'subtotal', 'discount_amount',
        'shipping_amount', 'shipping_method_id', 'tax_amount', 'cod_fee', 'total', 'store_credit_used',
        'store_credit_returned', 'amount_payable', 'payment_method', 'payment_status', 'fulfillment_status',
        'shipping_status', 'tracking_number', 'tracking_url', 'shiprocket_order_id', 'notes', 'shipping_address',
        'billing_address', 'cancel_reason', 'cancelled_at', 'delivered_at', 'refund_status', 'refund_method',
        'refunded_amount', 'refund_completed_at', 'pending_refund', 'recall',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coupon_id' => 'integer',
        'coupon_restored' => 'boolean',
        'subtotal' => 'integer',
        'discount_amount' => 'integer',
        'shipping_amount' => 'integer',
        'shipping_method_id' => 'integer',
        'tax_amount' => 'integer',
        'cod_fee' => 'integer',
        'total' => 'integer',
        'store_credit_used' => 'integer',
        'store_credit_returned' => 'boolean',
        'amount_payable' => 'integer',
        'refunded_amount' => 'integer',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'pending_refund' => 'array',
        'recall' => 'array',
        'cancelled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'refund_completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('at')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** Storefront derived badge (guide §24.1 deriveOrderStatus). */
    public function derivedStatus(): string
    {
        if ($this->fulfillment_status === 'returned') {
            return 'returned';
        }
        if ($this->fulfillment_status === 'cancelled' || in_array($this->payment_status, ['failed', 'refunded'], true)) {
            return 'cancelled';
        }
        if ($this->shipping_status === 'delivered') {
            return 'delivered';
        }
        if ($this->shipping_status === 'shipped') {
            return 'shipped';
        }

        return 'processing';
    }

    public function isOnlinePayment(): bool
    {
        return in_array($this->payment_method, self::ONLINE_METHODS, true);
    }
}
