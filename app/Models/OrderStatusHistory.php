<?php

namespace App\Models;

class OrderStatusHistory extends BaseModel
{
    public $timestamps = false;

    protected $table = 'order_status_history';

    protected $fillable = ['order_id', 'at', 'by', 'action', 'note'];

    protected $casts = ['at' => 'datetime'];
}
