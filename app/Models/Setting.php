<?php

namespace App\Models;

class Setting extends BaseModel
{
    public const CREATED_AT = null;

    protected $primaryKey = 'section';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['section', 'data', 'updated_at'];

    protected $casts = [
        'data' => 'array',
        'updated_at' => 'datetime',
    ];
}
