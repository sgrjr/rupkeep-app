<?php

namespace App\Listeners;

use App\Events\JobAssigned;
use App\Mail\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SendJobAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(JobAssigned $event): void
    {
        $driver = $event->driver;
        $recipient = $this->resolveAddress($driver->notification_address, $driver->email);

        if (! $recipient) {
            return;
        }

        $job = $event->job;

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

        Mail::to($recipient)->send(
            new UserNotification($message, $subject)
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


