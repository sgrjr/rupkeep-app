<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Render a timestamp in the operator's display timezone (config
 * app.display_timezone, default America/New_York) instead of the server's
 * UTC. Timestamps are stored in UTC; this converts at the point of display.
 *
 * Defensive by design: accepts a Carbon, a DateTime, a raw DB string, or null,
 * and never throws in a view — an unparseable value is returned as-is and an
 * empty value yields ''. Some columns (e.g. UserLog started_at/ended_at,
 * PilotCarJob scheduled_*) are not cast to Carbon, so a plain
 * ->setTimezone() would fatal; this handles them.
 */
class LocalTime
{
    /** Default: 3:04 PM EDT 7/13/2026 */
    public const DEFAULT_FORMAT = 'g:i A T n/j/Y';

    public static function format($value, string $format = self::DEFAULT_FORMAT, string $default = ''): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        try {
            $dt = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse($value);
        } catch (\Throwable) {
            return (string) $value;
        }

        return $dt->copy()
            ->setTimezone(config('app.display_timezone', 'America/New_York'))
            ->format($format);
    }

    /** Date only: 7/13/2026 */
    public static function date($value, string $default = ''): string
    {
        return self::format($value, 'n/j/Y', $default);
    }

    /** Longer date: Jul 13, 2026 */
    public static function mediumDate($value, string $default = ''): string
    {
        return self::format($value, 'M j, Y', $default);
    }
}
