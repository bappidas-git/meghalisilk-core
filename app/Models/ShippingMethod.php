<?php

namespace App\Models;

class ShippingMethod extends BaseModel
{
    protected $fillable = ['name', 'carrier', 'description', 'rate_type', 'flat_rate', 'free_above', 'estimated_days', 'is_active'];

    protected $casts = [
        'flat_rate' => 'integer',
        'free_above' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** Checkout's shipping formula (guide §23.1). */
    public function costFor(int $subtotal): int
    {
        if ($this->rate_type === 'free') {
            return 0;
        }
        if ($this->free_above && $subtotal >= $this->free_above) {
            return 0;
        }

        return (int) $this->flat_rate;
    }
}
