<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Daily maintenance-due digest to org admins/managers; the command itself
// re-reminds at most weekly per vehicle (TASK-041). Requires the host cron to
// run `php artisan schedule:run` every minute — see docs/DEPLOYMENT.md.
Schedule::command('vehicles:send-maintenance-reminders')->dailyAt('11:00');
