<?php

namespace App\Events;

use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A driver was taken off a job's log (TASK-459): the log's car_driver_id
 * moved to someone else. JobAssigned already tells the new driver; this
 * tells the one who no longer has the job, who otherwise kept a job they
 * thought was theirs.
 */
class JobUnassigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public PilotCarJob $job,
        public User $previousDriver,
        public ?UserLog $log = null
    ) {
    }
}
