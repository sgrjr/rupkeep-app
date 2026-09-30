<?php

namespace App\Listeners;

use App\Events\JobAssigned;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Notifications\JobUpdate;
use App\Support\JobSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Support\LocalTime;

class SendJobAssignedNotification implements ShouldQueue
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

    public function failed(JobAssigned $event, \Throwable $exception): void
    {
        Log::error(static::class.': gave up after '.$this->tries.' attempts', [
            'event' => JobAssigned::class,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);
    }
    use SendsNotificationMail;

    /**
     * Handle the event.
     */
    public function handle(JobAssigned $event): void
    {
        $job = $event->job;
        
        // Safety net: Only send notifications for jobs scheduled today or in the future
        // This prevents notifications when retroactively editing past jobs
        // "Today" is the operator's day, not the server's UTC day (TASK-464):
        // at 10 PM Eastern the UTC calendar has already turned over, and a
        // job earlier that same evening looked like yesterday's.
        $scheduledDate = LocalTime::parse($job->scheduled_pickup_at)?->startOfDay();
        if ($scheduledDate) {
            $today = LocalTime::today();

            // If scheduled pickup is in the past, skip notification
            if ($scheduledDate->lt($today)) {
                Log::info('SendJobAssignedNotification: Skipping notification for past job', [
                    'job_id' => $job->id,
                    'job_no' => $job->job_no,
                    'scheduled_pickup_at' => $job->scheduled_pickup_at,
                    'driver_id' => $event->driver->id,
                ]);
                return;
            }
        }
        
        $driver = $event->driver;
        $recipient = $this->resolveAddress($driver->notification_address, $driver->email);

        if (! $recipient) {
            return;
        }

        $scheduledAt = null;

        if ($job->scheduled_pickup_at) {
            $scheduledAt = LocalTime::dayDateTime($job->scheduled_pickup_at);
        }

        // Link straight to where the driver can act on the assignment: the log
        // edit page carries the Confirm/Deny buttons. Fall back to the job page
        // if we weren't handed the log (e.g. legacy callers / tests).
        $actionUrl = $event->log
            ? route('logs.edit', ['log' => $event->log->id])
            : route('my.jobs.show', ['job' => $job->id]);

        // The call to action depends on the log's real state. A freshly
        // assigned log is 'pending' (the default) and needs the driver to
        // accept it; but if the log was already confirmed before this fires,
        // there is nothing to accept — the message is purely informational.
        $needsConfirmation = ! $event->log || $event->log->approval_status === 'pending';

        // Best-effort send — a mail misconfig must not dead-letter the job.
        //
        // When the recipient is an email-to-SMS gateway address the whole
        // payload must fit in one 160-char SMS or iOS turns the overflow into an
        // unreadable attachment (TASK-352). Compose a short, URL-safe body and
        // send it subject-less as plain text. A real email address still gets
        // the full rich HTML template.
        if ($driver->usesSmsGateway()) {
            $this->mailSafely($recipient, new UserNotificationSms(
                JobSms::assigned($job, $actionUrl, $needsConfirmation)
            ));
        } else {
            $callToAction = $needsConfirmation
                ? sprintf('Open and tap Confirm to accept: %s', $actionUrl)
                : sprintf('View job details: %s', $actionUrl);

            $message = sprintf(
                "Job %s assigned to you.\nPickup: %s\nScheduled: %s\n%s",
                $job->job_no ?? ('#'.$job->id),
                $job->pickup_address ?: 'Not yet provided',
                $scheduledAt ?: 'Not scheduled',
                $callToAction
            );

            $subject = sprintf('Job Assigned: %s', $job->job_no ?? ('Job '.$job->id));

            $this->mailSafely($recipient, new UserNotification($message, $subject, 'mail.job-assigned', [
                'job' => $job,
                'driver' => $driver,
                'log' => $event->log,
            ], $job->organization?->name));
        }

        // Send push notification if driver has push subscriptions
        try {
            $pushTitle = sprintf('Job Assigned: %s', $job->job_no ?? ('Job #'.$job->id));
            $pushBody = sprintf(
                'Pickup: %s | Scheduled: %s',
                $job->pickup_address ?: 'TBD',
                $scheduledAt ?: 'Not scheduled'
            );

            $driver->notify(new JobUpdate($job, $pushTitle, $pushBody));
        } catch (\Exception $e) {
            Log::warning('SendJobAssignedNotification: Push notification failed', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveAddress(?string $preferred, ?string $fallback): ?string
    {
        $candidate = $preferred ?: $fallback;

        if (! $candidate) {
            return null;
        }

        return trim($candidate);
    }
}


