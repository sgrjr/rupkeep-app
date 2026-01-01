<?php

namespace App\Events;

use App\Models\PilotCarJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobWasCanceled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public PilotCarJob $job,
        public string $cancellationReason,
        public string $cancellationType
    ) {
    }
}

