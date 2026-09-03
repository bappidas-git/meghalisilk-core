<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Indian mobile number: optional +91 / 0 prefix, then 10 digits starting 6-9 (spaces and dashes ignored).
 */
class IndianMobile implements ValidationRule
{
    public static function matches(?string $value): bool
    {
        $digits = preg_replace('/[\s\-]/', '', (string) $value);

        return (bool) preg_match('/^(\+91|0)?[6-9]\d{9}$/', $digits);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! is_string($value) || ! self::matches($value)) {
            $fail('Please enter a valid Indian mobile number.');
        }
    }
}
