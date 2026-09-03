<?php

namespace App\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ApiResource extends JsonResource
{
    public static $wrap = null;

    /** ISO-8601 UTC with milliseconds, e.g. 2026-01-15T10:30:00.000Z */
    protected function iso(?DateTimeInterface $date): ?string
    {
        return $date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }

    /** `{}` instead of `[]` for empty JSON objects. */
    protected function object(?array $value): object|array
    {
        return empty($value) ? (object) [] : $value;
    }
}
