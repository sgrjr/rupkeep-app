<?php

namespace App\Listeners;

use App\Actions\SendUserNotification;
use App\Events\JobWasCanceled;
use App\Mail\UserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class NotifyAssignedDriversOfJobCancellation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(JobWasCanceled $event): void
    {
        \Log::info('NotifyAssignedDriversOfJobCancellation: Event received', [
            'job_id' => $event->job->id,
            'job_no' => $event->job->job_no,
            'cancellation_type' => $event->cancellationType,
        ]);

        $job = $event->job;
        
        // Get all unique drivers assigned to logs for this job
        $assignedDrivers = $job->logs()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter() // Remove null values
            ->unique('id'); // Remove duplicates

        \Log::info('NotifyAssignedDriversOfJobCancellation: Found drivers', [
            'job_id' => $job->id,
            'driver_count' => $assignedDrivers->count(),
            'driver_ids' => $assignedDrivers->pluck('id')->toArray(),
        ]);

        if ($assignedDrivers->isEmpty()) {
            \Log::warning('NotifyAssignedDriversOfJobCancellation: No drivers found for job', [
                'job_id' => $job->id,
                'logs_count' => $job->logs()->count(),
            ]);
            return;
        }

        // Build cancellation type description
        $cancellationTypeDescription = $this->getCancellationTypeDescription($event->cancellationType);

        // Build message for each driver
        foreach ($assignedDrivers as $driver) {
            // Prefer SMS gateway address, fallback to email
            $recipient = $driver->getSmsGatewayAddress() ?? $driver->email;

            if (! $recipient) {
                continue;
            }

            $scheduledAt = null;
            if ($job->scheduled_pickup_at) {
                $scheduledAt = Carbon::parse($job->scheduled_pickup_at)->toDayDateTimeString();
            }

            $message = sprintf(
                "Hello %s,\n\nJob %s has been canceled.\n\nJob Details:\n- Job #: %s\n- Load #: %s\n- Pickup: %s\n- Delivery: %s\n- Scheduled Pickup: %s\n\nCancellation Details:\n- Reason: %s\n- Type: %s\n\nPlease do not proceed with this job. If you have any questions, contact your manager.",
                $driver->name,
                $job->job_no ?? ('#'.$job->id),
                $job->job_no ?? ('#'.$job->id),
                $job->load_no ?: 'Not provided',
                $job->pickup_address ?: 'Not yet provided',
                $job->delivery_address ?: 'Not yet provided',
                $scheduledAt ?: 'Not scheduled',
                $event->cancellationReason,
                $cancellationTypeDescription
            );

            $subject = sprintf('Job Canceled: %s', $job->job_no ?? ('Job '.$job->id));

            \Log::info('NotifyAssignedDriversOfJobCancellation: Sending notification', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'recipient' => $recipient,
            ]);

            // Use SendUserNotification action for consistency
            // SMS gateway addresses use Brevo, regular emails use Laravel Mail
            SendUserNotification::to($driver, $message, $subject);
            
            \Log::info('NotifyAssignedDriversOfJobCancellation: Notification sent', [
                'job_id' => $job->id,
                'driver_id' => $driver->id,
            ]);
        }
    }

    /**
     * Get human-readable description of cancellation type
     */
    private function getCancellationTypeDescription(string $type): string
    {
        return match($type) {
            'show_no_go' => 'Show But No-Go ($225.00)',
            'cancellation_24hr' => 'Cancellation Within 24hrs ($150.00)',
            'cancel_without_billing' => 'Cancel Without Billing (No charge)',
            default => 'Auto-determined',
        };
    }

}
