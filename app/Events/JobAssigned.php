<?php

namespace App\Events;

use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public PilotCarJob $job,
        public User $driver
    ) {
    }
}


