<?php

// The /setup web console runs `db:reset` (migrate:fresh + reseed). It is OFF
// unless SETUP_CONSOLE_ENABLED=true is set explicitly, and even then the
// routes require a signed-in super user on top of the shared password
// (TASK-424). Leave it off on production.
return [
    'username' => env('SETUP_USERNAME', 'setup'),
    'password' => env('SETUP_PASSWORD'),
    'enabled' => (bool) env('SETUP_CONSOLE_ENABLED', false),
];
