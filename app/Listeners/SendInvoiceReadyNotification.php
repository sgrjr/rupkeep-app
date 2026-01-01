<?php

namespace App\Listeners;

use App\Events\InvoiceReady;
use App\Mail\UserNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendInvoiceReadyNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(InvoiceReady $event): void
    {
        $invoice = $event->invoice->loadMissing('organization.users', 'customer');

        $recipients = $this->collectRecipients($invoice);

        if ($recipients->isEmpty()) {
            return;
        }

        $subject = sprintf('Invoice Ready: %s', $invoice->invoice_number);

        $message = sprintf(
            "Invoice %s is ready for review.\nCustomer: %s\nTotal Due: %s\n\nSign in to Rupkeep to approve or send the invoice.",
            $invoice->invoice_number,
            optional($invoice->customer)->name ?: 'Unknown customer',
            number_format($invoice->values['total'] ?? 0, 2)
        );

        $recipients->each(function (string $address) use ($subject, $message, $invoice) {
            Mail::to($address)->send(new UserNotification($message, $subject, 'mail.invoice-ready', [
                'invoice' => $invoice,
            ]));
        });
    }

    private function collectRecipients($invoice)
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
            ->map(function (User $user) {
                $address = $user->notification_address ?: $user->email;

                return $address ? trim($address) : null;
            })
            ->filter()
            ->unique()
            ->values();
    }
}


