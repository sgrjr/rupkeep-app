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

    /** For notifications: Wed, Sep 30, 2026 7:00 AM EDT */
    public const DAY_DATE_TIME_FORMAT = 'D, M j, Y g:i A T';

    public static function timezone(): string
    {
        // JobSms is covered by a container-less unit test; without an app
        // there is no config to read, and the default is the answer anyway.
        try {
            return config('app.display_timezone', 'America/New_York') ?: 'America/New_York';
        } catch (\Throwable) {
            return 'America/New_York';
        }
    }

    /**
     * The value as a Carbon in the display timezone, or null when it is empty
     * or unparseable. For date arithmetic ("is this before today?") that must
     * happen in the operator's day, not the server's.
     */
    public static function parse($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $dt = $value instanceof \DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }

        return $dt->copy()->setTimezone(self::timezone());
    }

    /** Midnight today in the display timezone. */
    public static function today(): Carbon
    {
        return Carbon::now(self::timezone())->startOfDay();
    }

    /**
     * The notification form, in the driver's timezone (TASK-464). Every text
     * and email used Carbon's toDayDateTimeString() on the raw UTC value, so
     * a 7:00 AM job read as 11:00 AM in the driver's hand.
     */
    public static function dayDateTime($value, string $default = ''): string
    {
        return self::format($value, self::DAY_DATE_TIME_FORMAT, $default);
    }

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
            ->setTimezone(self::timezone())
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
