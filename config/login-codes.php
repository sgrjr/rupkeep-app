<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Expiration (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a one-time login code stays redeemable. The window covers both
    | ways of redeeming it — the code typed into the verify form and the
    | one-click link emailed alongside it — because they share a row.
    |
    | Defaults to 2 hours (TASK-319). Wide enough that someone who checks mail
    | on a phone an hour later still gets in, tight enough that a sign-in link
    | sitting in an inbox does not stay live all day.
    |
    */
    'expires_after_minutes' => env('LOGIN_CODE_EXPIRY_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Code Length
    |--------------------------------------------------------------------------
    |
    | The number of characters for the typed code. Codes are drawn from an
    | unambiguous uppercase alphabet — no O/0, I/1, S/5 — since people read
    | these off a screen and retype them. The emailed link does not use this
    | code; it carries its own high-entropy `link_token`.
    |
    */
    'code_length' => env('LOGIN_CODE_LENGTH', 8),
];
