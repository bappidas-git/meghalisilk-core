<?php

namespace App\Models;

class ProductImage extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'url', 'sort_order'];
}
