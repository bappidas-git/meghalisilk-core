<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'sku', 'short_description', 'description', 'category_id', 'brand',
        'price', 'compare_price', 'cost_price', 'stock', 'low_stock_threshold', 'weight',
        'dim_length', 'dim_width', 'dim_height', 'tags', 'featured', 'trending', 'hot', 'is_active',
        'rating', 'total_reviews', 'meta_title', 'meta_description',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'price' => 'integer',
        'compare_price' => 'integer',
        'cost_price' => 'integer',
        'stock' => 'integer',
        'low_stock_threshold' => 'integer',
        'weight' => 'float',
        'dim_length' => 'float',
        'dim_width' => 'float',
        'dim_height' => 'float',
        'tags' => 'array',
        'featured' => 'boolean',
        'trending' => 'boolean',
        'hot' => 'boolean',
        'is_active' => 'boolean',
        'rating' => 'float',
        'total_reviews' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const STOREFRONT_RELATIONS = ['images', 'variants', 'related', 'frequentlyBoughtTogether'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_links', 'product_id', 'linked_product_id')
            ->wherePivot('type', 'related')
            ->withPivot(['type', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function frequentlyBoughtTogether(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_links', 'product_id', 'linked_product_id')
            ->wherePivot('type', 'fbt')
            ->withPivot(['type', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function faqs(): BelongsToMany
    {
        return $this->belongsToMany(Faq::class, 'faq_product');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Case-insensitive search over name, shortDescription, brand and tags (guide §18). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(short_description, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(brand, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(tags, \'\')) LIKE ?', [$like]);
        });
    }

    public function variantByKey(?string $key): ?ProductVariant
    {
        if ($key === null || $key === '') {
            return null;
        }

        return $this->variants->firstWhere('variant_key', $key);
    }

    public function dimensions(): ?array
    {
        if ($this->dim_length === null && $this->dim_width === null && $this->dim_height === null) {
            return null;
        }

        return [
            'length' => $this->dim_length + 0,
            'width' => $this->dim_width + 0,
            'height' => $this->dim_height + 0,
        ];
    }
}
