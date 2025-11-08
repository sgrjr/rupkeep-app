<?php

namespace App\Listeners;

use App\Events\InvoiceFlagged;
use App\Mail\UserNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendInvoiceFlaggedNotification implements ShouldQueue
{
    use InteractsWithQueue;

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

        $message = sprintf(
            "Invoice %s was flagged for attention.\nComment by %s: %s\n\nSign in to Rupkeep to review and respond.",
            $invoice->invoice_number,
            optional($comment->user)->name ?: 'Unknown user',
            Str::limit($comment->body, 240)
        );

        $subject = sprintf('Invoice Flagged: %s', $invoice->invoice_number);

        $recipients->each(function (string $address) use ($message, $subject) {
            Mail::to($address)->send(new UserNotification($message, $subject));
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


