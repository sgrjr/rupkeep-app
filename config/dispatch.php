<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Remote (production) Dispatch endpoint
    |--------------------------------------------------------------------------
    |
    | The local dev environment uses these to sync task state to/from the
    | canonical production database via `php artisan dispatch:pull` and
    | `dispatch:push`. Leave blank in production itself.
    |
    | Generate a token on production by visiting Jetstream's API token page
    | (signed in as a super user) and paste it as DISPATCH_REMOTE_TOKEN.
    */
    'remote' => [
        'url' => env('DISPATCH_REMOTE_URL'),
        'token' => env('DISPATCH_REMOTE_TOKEN'),
        'timeout' => env('DISPATCH_REMOTE_TIMEOUT', 30),
    ],
];
