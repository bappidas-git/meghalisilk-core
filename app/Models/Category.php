<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'image', 'parent_id', 'is_active', 'sort_order',
        'show_in_main_menu', 'menu_order',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'show_in_main_menu' => 'boolean',
        'menu_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Ids of this category and every descendant (parent-includes-children rule). */
    public function descendantIdsIncludingSelf(): array
    {
        $all = static::query()->select(['id', 'parent_id'])->get()->groupBy('parent_id');
        $ids = [];
        $stack = [$this->id];

        while ($stack) {
            $id = array_pop($stack);
            $ids[] = $id;
            foreach ($all->get($id, collect()) as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    /** True when $candidateParentId is this category or one of its descendants (cycle guard). */
    public function wouldCreateCycle(?int $candidateParentId): bool
    {
        if ($candidateParentId === null) {
            return false;
        }

        return in_array($candidateParentId, $this->descendantIdsIncludingSelf(), true);
    }
}
