<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token lifetime
    |--------------------------------------------------------------------------
    |
    | Sanctum tokens issued by the login endpoints expire after this many days.
    | Customers who tick "Remember me" get the longer lifetime. Empty = never.
    |
    */

    'token_ttl_days' => env('STORE_TOKEN_TTL_DAYS', 30),
    'token_ttl_remember_days' => env('STORE_TOKEN_TTL_REMEMBER_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Online orders without a payment gateway (guide §22.3)
    |--------------------------------------------------------------------------
    |
    | Option A (default, secure): online orders are created with
    | paymentStatus "pending" and the admin uses "Mark as Paid".
    | Option B (parity with the mock): honour the client's "paid" and stamp a
    | placeholder transaction id. Only acceptable while no real money moves.
    |
    */

    'trust_client_payment_status' => (bool) env('STORE_TRUST_CLIENT_PAYMENT_STATUS', false),

    /*
    |--------------------------------------------------------------------------
    | Dashboard revenue (guide §30)
    |--------------------------------------------------------------------------
    |
    | The mock sums every order's total. Set to true to exclude cancelled and
    | refunded orders from totalRevenue.
    |
    */

    'revenue_excludes_cancelled' => (bool) env('STORE_REVENUE_EXCLUDES_CANCELLED', false),

];
