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

        // Set to false to skip SSL certificate verification on the outbound
        // HTTP calls. Useful as a dev-machine workaround when the local PHP
        // install lacks a CA bundle (Windows PHP without curl.cainfo set).
        // Leave at true (the default) in production.
        'verify_ssl' => env('DISPATCH_REMOTE_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-capture uncaught exceptions as Dispatch bug tasks
    |--------------------------------------------------------------------------
    |
    | When enabled, an uncaught (500-level) application exception opens a `bug`
    | task in `triage` labeled `source:exception`, so runtime failures reach the
    | backlog instead of only living in the logs. Recurring identical exceptions
    | are deduped onto the existing open task. See ExceptionCaptureService.
    */
    'auto_capture' => [
        // Master switch. Set DISPATCH_AUTO_CAPTURE=true on production.
        'enabled' => env('DISPATCH_AUTO_CAPTURE', false),

        // Even when enabled, only these environments capture — this keeps a
        // developer's constant local exceptions from spamming the board.
        'environments' => ['production'],

        // Label attached to every auto-created task so the triage queue can
        // filter them (mirrors the `source:feedback` convention).
        'label' => 'source:exception',
    ],
];
