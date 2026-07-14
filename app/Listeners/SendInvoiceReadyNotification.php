<?php

namespace App\Listeners;

use App\Events\InvoiceReady;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendInvoiceReadyNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use SendsNotificationMail;

    /**
     * Handle the event.
     */
    public function handle(InvoiceReady $event): void
    {
        $invoice = $event->invoice->loadMissing('organization.users', 'customer');
        $orgName = $invoice->organization?->name ?: 'your organization';

        $orgUsers = ($invoice->organization?->users ?? collect())
            ->whereIn('organization_role', [User::ROLE_ADMIN, User::ROLE_EMPLOYEE_MANAGER])
            ->values();

        $customerUsers = User::query()
            ->where('customer_id', $invoice->customer_id)
            ->get();

        // The recipient's role decides where they land: org staff get the
        // internal edit view (review / approve / send); the customer gets
        // their own portal view. Sending a customer the staff URL would drop
        // them on an authorization wall, so both the URL and the copy follow
        // the recipient, not the invoice.
        $seen = [];

        $deliver = function ($users, bool $isOrgUser) use (&$seen, $invoice, $orgName): void {
            foreach ($users as $user) {
                $address = trim($user->notification_address ?: $user->email ?: '');

                if ($address === '' || isset($seen[$address])) {
                    continue;
                }

                $seen[$address] = true;

                $this->sendInvoiceReady($address, $invoice, $orgName, $isOrgUser);
            }
        };

        $deliver($orgUsers, true);        // staff first, so they win the dedupe
        $deliver($customerUsers, false);
    }

    private function sendInvoiceReady(string $address, $invoice, string $orgName, bool $isOrgUser): void
    {
        $url = $isOrgUser
            ? route('my.invoices.edit', ['invoice' => $invoice->id])
            : route('customer.invoices.show', ['invoice' => $invoice->id]);

        $subject = sprintf('Invoice Ready: %s', $invoice->invoice_number);
        $total = number_format((float) ($invoice->values['total'] ?? 0), 2);

        $message = $isOrgUser
            ? sprintf(
                "Invoice %s is ready for review.\nCustomer: %s\nTotal Due: %s\nReview or send it: %s",
                $invoice->invoice_number,
                optional($invoice->customer)->name ?: 'Unknown customer',
                $total,
                $url
            )
            : sprintf(
                "Invoice %s from %s is ready.\nTotal Due: %s\nView your invoice: %s",
                $invoice->invoice_number,
                $orgName,
                $total,
                $url
            );

        $this->mailSafely($address, new UserNotification($message, $subject, 'mail.invoice-ready', [
            'invoice' => $invoice,
            'invoiceUrl' => $url,
            'isOrgUser' => $isOrgUser,
            'orgName' => $orgName,
        ], $orgName));
    }
}


