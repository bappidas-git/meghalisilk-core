<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'label', 'first_name', 'last_name', 'phone', 'address_line1', 'address_line2',
        'city', 'state', 'postal_code', 'country', 'is_default', 'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
