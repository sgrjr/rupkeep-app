<?php

use Illuminate\Support\Facades\Schedule;

// The default hourly `inspire` quote is gone (TASK-466): it was the only
// other scheduled entry and ran nowhere useful.

// Daily maintenance-due digest to org admins/managers; the command itself
// re-reminds at most weekly per vehicle (TASK-041). Requires the host cron to
// run `php artisan schedule:run` every minute — see docs/DEPLOYMENT.md.
Schedule::command('vehicles:send-maintenance-reminders')->dailyAt('11:00');

// Every fifteen minutes: is the worker alive and the queue draining, and are
// there failed jobs? Mails the super users on a problem (at most once per six
// hours for the same problem) and once when it clears (TASK-469).
Schedule::command('queue:health --notify')->everyFifteenMinutes()->withoutOverlapping();
