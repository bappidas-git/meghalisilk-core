<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'product_id', 'variant_key', 'variant_name', 'name', 'image', 'sku', 'price', 'quantity', 'subtotal'];

    protected $casts = [
        'product_id' => 'integer',
        'price' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
