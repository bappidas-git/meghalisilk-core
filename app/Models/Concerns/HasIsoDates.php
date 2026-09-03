<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Serialize every date as ISO-8601 UTC with milliseconds (2026-01-15T10:30:00.000Z)
 * and store them with millisecond precision (DATETIME(3)).
 */
trait HasIsoDates
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }
}
