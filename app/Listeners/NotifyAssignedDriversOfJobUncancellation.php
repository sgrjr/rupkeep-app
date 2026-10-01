<?php

namespace App\Listeners;

use App\Events\JobWasUncanceled;
use App\Notifications\JobUpdate;
use App\Support\JobSms;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use App\Support\LocalTime;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendUserMessage;

class NotifyAssignedDriversOfJobUncancellation implements ShouldQueue
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

    public function failed(JobWasUncanceled $event, \Throwable $exception): void
    {
        Log::error(static::class.': gave up after '.$this->tries.' attempts', [
            'event' => JobWasUncanceled::class,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);
    }

    /**
     * Handle the event.
     */
    public function handle(JobWasUncanceled $event): void
    {
        \Log::info('NotifyAssignedDriversOfJobUncancellation: Event received', [
            'job_id' => $event->job->id,
            'job_no' => $event->job->job_no,
            'previous_reason' => $event->previousCancellationReason,
        ]);

        $job = $event->job;
        
        // Get all unique drivers assigned to logs for this job
        $assignedDrivers = $job->logs()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter() // Remove null values
            ->unique('id'); // Remove duplicates

        \Log::info('NotifyAssignedDriversOfJobUncancellation: Found drivers', [
            'job_id' => $job->id,
            'driver_count' => $assignedDrivers->count(),
            'driver_ids' => $assignedDrivers->pluck('id')->toArray(),
        ]);

        if ($assignedDrivers->isEmpty()) {
            \Log::warning('NotifyAssignedDriversOfJobUncancellation: No drivers found for job', [
                'job_id' => $job->id,
                'logs_count' => $job->logs()->count(),
            ]);
            return;
        }

        // Build message for each driver
        foreach ($assignedDrivers as $driver) {
            // Prefer SMS gateway address, fallback to email
            $recipient = $driver->getSmsGatewayAddress() ?? $driver->email;

            if (! $recipient) {
                \Log::warning('NotifyAssignedDriversOfJobUncancellation: No recipient for driver', [
                    'job_id' => $job->id,
                    'driver_id' => $driver->id,
                    'driver_email' => $driver->email,
                    'notification_address' => $driver->notification_address,
                ]);
                continue;
            }

            $scheduledAt = null;
            if ($job->scheduled_pickup_at) {
                $scheduledAt = LocalTime::dayDateTime($job->scheduled_pickup_at); // driver's timezone (TASK-464)
            }

            // A carrier SMS gateway needs a body that fits one 160-char text
            // (TASK-352); a real mailbox gets the full detailed message.
            if ($driver->usesSmsGateway()) {
                $message = JobSms::reactivated(
                    $job,
                    route('my.jobs.show', ['job' => $job->id])
                );
            } else {
                $message = sprintf(
                    "Hello %s,\n\nJob %s has been REACTIVATED (uncanceled).\n\nJob Details:\n- Job #: %s\n- Load #: %s\n- Pickup: %s\n- Delivery: %s\n- Scheduled Pickup: %s\n\nThis job is now active again. Please proceed as scheduled. If you have any questions, contact your manager.",
                    $driver->name,
                    $job->job_no ?? ('#'.$job->id),
                    $job->job_no ?? ('#'.$job->id),
                    $job->load_no ?: 'Not provided',
                    $job->pickup_address ?: 'Not yet provided',
                    $job->delivery_address ?: 'Not yet provided',
                    $scheduledAt ?: 'Not scheduled'
                );
            }

            $subject = sprintf('Job Reactivated: %s', $job->job_no ?? ('Job '.$job->id));

            \Log::info('NotifyAssignedDriversOfJobUncancellation: Sending notification', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'recipient' => $recipient,
            ]);

            // Use SendUserNotification action for consistency
            // SMS gateway addresses use Brevo, regular emails use Laravel Mail
            SendUserMessage::dispatch($driver, $message, $subject); // one queued job per recipient (TASK-465)

            // Phone tray too (TASK-467).
            try {
                $driver->notify(new JobUpdate($job, $subject, 'This job is active again. Proceed as scheduled.'));
            } catch (\Throwable $e) {
                \Log::warning('NotifyAssignedDriversOfJobUncancellation: push notification failed', [
                    'job_id' => $job->id,
                    'driver_id' => $driver->id,
                    'error' => $e->getMessage(),
                ]);
            }

            \Log::info('NotifyAssignedDriversOfJobUncancellation: Notification sent', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
            ]);
        }
    }
}
