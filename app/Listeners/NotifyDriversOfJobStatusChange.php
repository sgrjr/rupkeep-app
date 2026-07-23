<?php

namespace App\Listeners;

use App\Actions\SendUserNotification;
use App\Events\JobStatusChanged;
use App\Models\PilotCarJob;
use App\Notifications\JobUpdate;
use App\Support\JobSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Notifies every driver assigned to a job when the job's status changes
 * (TASK-311 / TASK-312).
 *
 * Delivery mirrors the assignment notification flow
 * ({@see SendJobAssignedNotification}): a message routed through the
 * email-to-SMS gateway (short body composed by {@see JobSms} so it stays under
 * 160 chars) or regular email, plus the {@see JobUpdate} web-push/database
 * notification.
 *
 * Cancellation and reactivation transitions are intentionally skipped here:
 * they already have dedicated driver notifiers
 * ({@see NotifyAssignedDriversOfJobCancellation} /
 * {@see NotifyAssignedDriversOfJobUncancellation}), and firing both would text
 * every driver twice. In practice this listener therefore handles the
 * ACTIVE -> COMPLETED transition (a job that was invoiced / closed out).
 */
class NotifyDriversOfJobStatusChange implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(JobStatusChanged $event): void
    {
        // Transitions into or out of a cancelled state are owned by the
        // cancel/uncancel listeners.
        if (self::isCancellation($event->from) || self::isCancellation($event->to)) {
            return;
        }

        $job = $event->job;

        $drivers = $job->logs()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        if ($drivers->isEmpty()) {
            return;
        }

        $actionUrl = route('my.jobs.show', ['job' => $job->id]);
        $subject = sprintf('Job %s Update', $job->job_no ?? ('#'.$job->id));

        foreach ($drivers as $driver) {
            $body = $driver->usesSmsGateway()
                ? JobSms::statusChanged($job, $event->to, $actionUrl)
                : $this->emailBody($job, $event, $actionUrl);

            // Routes to the SMS gateway (short body) or email, exactly like the
            // cancellation notifications.
            SendUserNotification::to($driver, $body, $subject);

            // Web-push / database channel, matching the assignment flow.
            try {
                $driver->notify(new JobUpdate(
                    $job,
                    $subject,
                    sprintf('This job is now %s.', JobSms::statusPhrase($event->to))
                ));
            } catch (\Throwable $e) {
                Log::warning('NotifyDriversOfJobStatusChange: push notification failed', [
                    'job_id' => $job->id,
                    'driver_id' => $driver->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function emailBody(PilotCarJob $job, JobStatusChanged $event, string $actionUrl): string
    {
        $scheduledAt = $job->scheduled_pickup_at
            ? Carbon::parse($job->scheduled_pickup_at)->toDayDateTimeString()
            : 'Not scheduled';

        return sprintf(
            "Job %s is now %s.\n\nJob Details:\n- Job #: %s\n- Load #: %s\n- Pickup: %s\n- Delivery: %s\n- Scheduled Pickup: %s\n\nView job: %s",
            $job->job_no ?? ('#'.$job->id),
            JobSms::statusPhrase($event->to),
            $job->job_no ?? ('#'.$job->id),
            $job->load_no ?: 'Not provided',
            $job->pickup_address ?: 'Not yet provided',
            $job->delivery_address ?: 'Not yet provided',
            $scheduledAt,
            $actionUrl
        );
    }

    private static function isCancellation(string $status): bool
    {
        return str_starts_with($status, 'CANCELLED');
    }
}
