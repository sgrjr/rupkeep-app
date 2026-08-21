<?php

namespace App\Listeners;

use App\Events\LogCompleted;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * TASK-364: tell the office a driver has finished, so the job can be reviewed
 * and sent for invoicing. Fans out to the organization's admins and managers,
 * mirroring SendInvoiceReadyNotification.
 */
class SendLogCompletedNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SendsNotificationMail;

    public function handle(LogCompleted $event): void
    {
        $log = $event->log->loadMissing('job.customer', 'organization.users', 'user');
        $job = $log->job;

        if (! $job) {
            return;
        }

        $orgName = $log->organization?->name ?: 'your organization';

        $recipients = ($log->organization?->users ?? collect())
            ->whereIn('organization_role', [User::ROLE_ADMIN, User::ROLE_EMPLOYEE_MANAGER]);

        $url = route('logs.edit', ['log' => $log->id]);
        $driverName = $log->user?->name ?: $event->completedBy->name;
        $subject = sprintf('Job Complete: %s', $job->job_no ?: ('#' . $job->id));

        $message = sprintf(
            "%s marked their log complete for job %s.\nCustomer: %s\nReady to review and invoice: %s",
            $driverName,
            $job->job_no ?: ('#' . $job->id),
            optional($job->customer)->name ?: 'Unknown customer',
            $url
        );

        $seen = [];

        foreach ($recipients as $user) {
            $address = trim($user->notification_address ?: $user->email ?: '');

            // The driver who just completed the log does not need to be told
            // they completed it — but a manager completing someone else's log
            // is a different person from the log's driver, so dedupe on the
            // address rather than on the role.
            if ($address === '' || isset($seen[$address]) || $user->id === $event->completedBy->id) {
                continue;
            }

            $seen[$address] = true;

            $this->mailSafely($address, new UserNotification($message, $subject, 'mail.notification-text', [], $orgName));
        }
    }
}
