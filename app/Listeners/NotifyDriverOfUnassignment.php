<?php

namespace App\Listeners;

use App\Events\JobUnassigned;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Notifications\JobUpdate;
use App\Support\JobSms;
use App\Support\LocalTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Tell a driver they are no longer on a job (TASK-459).
 *
 * Mirrors SendJobAssignedNotification: the email-to-SMS gateway gets a
 * short GSM-7 body, a real address gets the email, and the web-push /
 * database channel gets a JobUpdate. Past jobs are skipped the same way, so
 * correcting who drove last month does not text anyone.
 */
class NotifyDriverOfUnassignment implements ShouldQueue
{
    use InteractsWithQueue;
    use SendsNotificationMail;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public bool $afterCommit = true;

    public bool $deleteWhenMissingModels = true;

    public function failed(JobUnassigned $event, \Throwable $exception): void
    {
        Log::error(static::class.': gave up after '.$this->tries.' attempts', [
            'event' => JobUnassigned::class,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);
    }

    public function handle(JobUnassigned $event): void
    {
        $job = $event->job;
        $driver = $event->previousDriver;

        $scheduledDate = LocalTime::parse($job->scheduled_pickup_at)?->startOfDay();
        if ($scheduledDate && $scheduledDate->lt(LocalTime::today())) {
            Log::info('NotifyDriverOfUnassignment: Skipping notification for past job', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
            ]);

            return;
        }

        $recipient = trim((string) ($driver->notification_address ?: $driver->email));

        if ($recipient === '') {
            Log::warning('NotifyDriverOfUnassignment: driver has no notification address or email; nothing sent', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
            ]);

            return;
        }

        $actionUrl = route('my.jobs.show', ['job' => $job->id]);
        $scheduledAt = $job->scheduled_pickup_at ? LocalTime::dayDateTime($job->scheduled_pickup_at) : null;
        $jobRef = $job->job_no ?? ('#'.$job->id);

        if ($driver->usesSmsGateway()) {
            $this->mailSafely($recipient, new UserNotificationSms(JobSms::unassigned($job, $actionUrl)));
        } else {
            $message = sprintf(
                "You are no longer assigned to job %s.\nPickup: %s\nScheduled: %s\nIf you believe this is a mistake, contact dispatch. Job page: %s",
                $jobRef,
                $job->pickup_address ?: 'Not provided',
                $scheduledAt ?: 'Not scheduled',
                $actionUrl
            );

            $this->mailSafely($recipient, new UserNotification(
                $message,
                sprintf('Removed from Job %s', $jobRef),
                'mail.notification-text',
                [],
                $job->organization?->name
            ));
        }

        try {
            $driver->notify(new JobUpdate(
                $job,
                sprintf('Removed from Job %s', $jobRef),
                'You are no longer assigned to this job.'
            ));
        } catch (\Throwable $e) {
            Log::warning('NotifyDriverOfUnassignment: push notification failed', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
