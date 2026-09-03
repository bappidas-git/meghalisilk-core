<?php

namespace App\Models;

class ReturnStatusHistory extends BaseModel
{
    public $timestamps = false;

    protected $table = 'return_status_history';

    protected $fillable = ['return_id', 'at', 'by', 'action', 'note'];

    protected $casts = ['at' => 'datetime'];
}
