<?php

namespace App\Listeners;

use App\Events\InvoiceFlagged;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SendInvoiceFlaggedNotification implements ShouldQueue
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

    public function failed(InvoiceFlagged $event, \Throwable $exception): void
    {
        Log::error(static::class.': gave up after '.$this->tries.' attempts', [
            'event' => InvoiceFlagged::class,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);
    }
    use SendsNotificationMail;

    /**
     * Handle the event.
     */
    public function handle(InvoiceFlagged $event): void
    {
        $comment = $event->comment->loadMissing('invoice.organization.users', 'invoice.customer', 'user');
        $invoice = $comment->invoice;

        if (! $invoice) {
            return;
        }

        $recipients = $this->collectRecipients($invoice, $comment->user);

        if ($recipients->isEmpty()) {
            return;
        }

        $orgName = $invoice->organization?->name ?: 'your organization';

        $message = sprintf(
            "Invoice %s was flagged for attention.\nComment by %s: %s\n\nSign in to %s to review and respond.",
            $invoice->invoice_number,
            optional($comment->user)->name ?: 'Unknown user',
            Str::limit($comment->body, 240),
            $orgName
        );

        $subject = sprintf('Invoice Flagged: %s', $invoice->invoice_number);

        $recipients->each(function (string $address) use ($message, $subject, $orgName) {
            $this->mailSafely($address, new UserNotification($message, $subject, 'mail.notification-text', [], $orgName));
        });
    }

    private function collectRecipients($invoice, ?User $author)
    {
        $organizationUsers = $invoice->organization?->users ?? collect();

        $organizationUsers = $organizationUsers
            ->whereIn('organization_role', [User::ROLE_ADMIN, User::ROLE_EMPLOYEE_MANAGER])
            ->values();

        $customerUsers = User::query()
            ->where('customer_id', $invoice->customer_id)
            ->get();

        return $organizationUsers
            ->merge($customerUsers)
            ->filter(fn (User $user) => ! $author || $user->isNot($author))
            ->map(function (User $user) {
                $address = $user->notification_address ?: $user->email;

                return $address ? trim($address) : null;
            })
            ->filter()
            ->unique()
            ->values();
    }
}


