<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Currency formatting with a graceful fallback.
 *
 * Prefers {@see Number::currency()} (which uses PHP's `intl` extension)
 * and falls back to a plain `$1,234.56` formatter when `intl` is not
 * available — so views don't 500 in environments without the extension.
 */
class Money
{
    public static function currency(float|int|string|null $amount, string $currency = 'USD', ?string $locale = null): string
    {
        $value = (float) ($amount ?? 0);

        if (extension_loaded('intl')) {
            return Number::currency($value, $currency, $locale);
        }

        $sign = $value < 0 ? '-' : '';
        $symbol = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency) . ' ';

        return $sign . $symbol . number_format(abs($value), 2);
    }
}
