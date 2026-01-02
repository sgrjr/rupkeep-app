<?php

namespace App\Listeners;

use App\Events\JobAssigned;
use App\Mail\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendJobAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(JobAssigned $event): void
    {
        $job = $event->job;
        
        // Safety net: Only send notifications for jobs scheduled today or in the future
        // This prevents notifications when retroactively editing past jobs
        if ($job->scheduled_pickup_at) {
            $scheduledDate = Carbon::parse($job->scheduled_pickup_at)->startOfDay();
            $today = Carbon::today()->startOfDay();
            
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
            $scheduledAt = Carbon::parse($job->scheduled_pickup_at)->toDayDateTimeString();
        }

        $message = sprintf(
            "Hello %s,\n\nYou have been assigned to job %s.\nPickup: %s\nDelivery: %s\nScheduled Pickup: %s\n\nSign in to Rupkeep to review the job details.",
            $driver->name,
            $job->job_no ?? ('#'.$job->id),
            $job->pickup_address ?: 'Not yet provided',
            $job->delivery_address ?: 'Not yet provided',
            $scheduledAt ?: 'Not scheduled'
        );

        $subject = sprintf('Job Assigned: %s', $job->job_no ?? ('Job '.$job->id));

        // Use standard Laravel Mail facade (respects mail configuration)
        Mail::to($recipient)->send(
            new UserNotification($message, $subject, 'mail.job-assigned', [
                'job' => $job,
                'driver' => $driver,
            ])
        );
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


