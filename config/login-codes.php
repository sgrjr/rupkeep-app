<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Expiration (minutes)
    |--------------------------------------------------------------------------
    |
    | One-time login codes will expire after this number of minutes unless
    | otherwise specified. Defaults to 24 hours.
    |
    */
    'expires_after_minutes' => env('LOGIN_CODE_EXPIRY_MINUTES', 60 * 24),

    /*
    |--------------------------------------------------------------------------
    | Code Length
    |--------------------------------------------------------------------------
    |
    | The number of characters for generated codes. Codes are generated from
    | secure random bytes and encoded using base32 (no confusing characters).
    |
    */
    'code_length' => env('LOGIN_CODE_LENGTH', 8),
];

