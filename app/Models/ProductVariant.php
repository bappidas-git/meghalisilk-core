<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'variant_key', 'name', 'price', 'stock', 'sku', 'attributes', 'swatch_hex', 'sort_order'];

    protected $casts = [
        'price' => 'integer',
        'stock' => 'integer',
        'attributes' => 'array',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
