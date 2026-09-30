<?php

namespace App\Listeners;

use App\Events\JobStatusChanged;
use App\Models\PilotCarJob;
use App\Notifications\JobUpdate;
use App\Support\JobSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Support\LocalTime;
use App\Jobs\SendUserMessage;

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

    /**
     * Retry a transient mail failure with a pause, then give up loudly
     * (TASK-465). tries/backoff/afterCommit/deleteWhenMissingModels are
     * copied onto the queued job by the event dispatcher.
     */
    public int $tries = 3;

    /** @var array<int, int> seconds before the second and third attempts */
    public array $backoff = [30, 120];

    /** Never before the row that fired the event is committed. */
    public bool $afterCommit = true;

    /** An event whose model was deleted before the worker ran is not a failure. */
    public bool $deleteWhenMissingModels = true;

    public function failed(JobStatusChanged $event, \Throwable $exception): void
    {
        Log::error(static::class.': gave up after '.$this->tries.' attempts', [
            'event' => JobStatusChanged::class,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);
    }

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
            SendUserMessage::dispatch($driver, $body, $subject); // one queued job per recipient (TASK-465)

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
        $scheduledAt = LocalTime::dayDateTime($job->scheduled_pickup_at, 'Not scheduled'); // driver's timezone (TASK-464)

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
