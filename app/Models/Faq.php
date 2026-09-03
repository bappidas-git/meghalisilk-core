<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Faq extends BaseModel
{
    public const PLACEMENTS = ['product', 'help', 'home'];

    protected $fillable = ['question', 'answer', 'placements', 'is_active', 'sort_order'];

    protected $casts = [
        'placements' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'faq_product');
    }
}
