<?php

namespace App\Listeners;

use App\Actions\SendUserNotification;
use App\Events\JobWasUncanceled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

class NotifyAssignedDriversOfJobUncancellation implements ShouldQueue
{
    use InteractsWithQueue;

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
                $scheduledAt = Carbon::parse($job->scheduled_pickup_at)->toDayDateTimeString();
            }

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

            $subject = sprintf('Job Reactivated: %s', $job->job_no ?? ('Job '.$job->id));

            \Log::info('NotifyAssignedDriversOfJobUncancellation: Sending notification', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'recipient' => $recipient,
            ]);

            // Use SendUserNotification action for consistency
            // SMS gateway addresses use Brevo, regular emails use Laravel Mail
            SendUserNotification::to($driver, $message, $subject);
            
            \Log::info('NotifyAssignedDriversOfJobUncancellation: Notification sent', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
            ]);
        }
    }
}
