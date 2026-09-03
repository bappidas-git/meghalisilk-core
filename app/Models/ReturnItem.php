<?php

namespace App\Models;

class ReturnItem extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['return_id', 'product_id', 'variant_key', 'variant_name', 'name', 'sku', 'price', 'quantity', 'subtotal'];

    protected $casts = [
        'product_id' => 'integer',
        'price' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
    ];
}
