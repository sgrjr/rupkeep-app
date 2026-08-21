<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver has marked their log finished (TASK-364), so the office can review
 * the job and bill it. Carries the actor separately from the log's assigned
 * driver, since a manager can also close a log out on a driver's behalf.
 */
class LogCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public UserLog $log,
        public User $completedBy,
    ) {
    }
}
