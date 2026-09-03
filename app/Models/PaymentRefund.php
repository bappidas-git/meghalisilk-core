<?php

namespace App\Models;

class PaymentRefund extends BaseModel
{
    public $timestamps = false;

    protected $fillable = ['payment_id', 'ref_key', 'amount', 'reason', 'at', 'by'];

    protected $casts = ['amount' => 'integer', 'at' => 'datetime'];
}
