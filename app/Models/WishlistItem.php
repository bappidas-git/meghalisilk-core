<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WishlistItem extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'product_id', 'created_at'];

    protected $casts = [
        'user_id' => 'integer',
        'product_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
