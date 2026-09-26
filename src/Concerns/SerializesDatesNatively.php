<?php

declare(strict_types=1);

namespace Brighten\ImmutableModel\Concerns;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Serialize dates with PHP's native formatter instead of Carbon's isoFormat().
 *
 * Eloquent's serializeDate() calls Carbon's toJSON(), which parses its format
 * string character by character. On a MySQL benchmark it was about two thirds
 * of the time of toArray(). This produces the same string, for example
 * "2024-01-01T00:00:00.000000Z", about 3 times faster.
 *
 * Carbon treats year 0 as invalid (toJSON() returns null) and uses a 6-digit
 * year format outside 1-9999. Those dates fall back to Eloquent's
 * implementation. A model that overrides serializeDate() keeps its own.
 */
trait SerializesDatesNatively
{
    private static ?DateTimeZone $utcTimeZone = null;

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param DateTimeInterface $date
     * @return string|null
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        // Carbon checks the year before converting to UTC, so check it here too.
        $year = (int) $date->format('Y');

        if ($year <= 0 || $year > 9999) {
            return parent::serializeDate($date);
        }

        return DateTimeImmutable::createFromInterface($date)
            ->setTimezone(self::$utcTimeZone ??= new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
