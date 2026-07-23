<?php

namespace App\Events;

use App\Models\PilotCarJob;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a pilot-car job's derived status changes — ACTIVE, COMPLETED,
 * CANCELLED (show but no-go) or CANCELLED_NO_GO (TASK-311).
 *
 * The status is a computed attribute ({@see PilotCarJob::getStatusAttribute()})
 * derived from `canceled_at` and invoice existence, not a stored column, so this
 * event is dispatched explicitly from the few places that mutate those inputs
 * (cancel / uncancel / invoice creation) via {@see self::fireIfChanged()}.
 */
class JobStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public PilotCarJob $job,
        public string $from,
        public string $to,
    ) {
    }

    /**
     * Dispatch the event only if the job's current status differs from the
     * status captured before the mutation.
     *
     * Callers capture `$from` (e.g. `$job->fresh()->status`) BEFORE they change
     * the job/invoices, run their mutation, then call this. The current status
     * is re-read from a fresh model so it reflects committed state rather than
     * any stale eager-loaded relations.
     */
    public static function fireIfChanged(PilotCarJob $job, string $from): void
    {
        $fresh = $job->fresh();

        if ($fresh === null) {
            return;
        }

        $to = $fresh->status;

        if ($to !== $from) {
            event(new self($fresh, $from, $to));
        }
    }
}
