<?php

namespace App\Support;

final class Money
{
    /**
     * Format whole rupees the way the storefront does (en-IN grouping): 9619 → "₹9,619", 100000 → "₹1,00,000".
     */
    public static function inr(int|float $amount): string
    {
        $amount = (int) round($amount);
        $sign = $amount < 0 ? '-' : '';
        $digits = (string) abs($amount);

        if (strlen($digits) <= 3) {
            return $sign.'₹'.$digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

        return $sign.'₹'.$rest.','.$last3;
    }

    public static function methodLabel(?string $method): string
    {
        return str_replace('_', ' ', (string) $method);
    }
}
