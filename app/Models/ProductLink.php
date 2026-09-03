<?php

namespace App\Models;

class ProductLink extends BaseModel
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['product_id', 'linked_product_id', 'type', 'sort_order'];
}
