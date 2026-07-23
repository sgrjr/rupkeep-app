<?php

namespace App\Support;

class Phone
{
    /**
     * Sanitized tel: href target — digits plus a leading '+' only.
     * Returns null when nothing dialable remains.
     */
    public static function tel(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $number);

        return preg_match('/[0-9]/', $digits) ? 'tel:'.$digits : null;
    }

    /**
     * Human-friendly rendering: 10-digit US numbers become (xxx) xxx-xxxx,
     * anything else passes through untouched.
     */
    public static function display(?string $number): ?string
    {
        if (! $number) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $number);

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        }

        return $number;
    }
}
