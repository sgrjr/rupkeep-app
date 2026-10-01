<?php

namespace App\Listeners;

use App\Events\InvoiceReady;
use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Mail\UserNotificationSms;
use App\Support\OfficeSms;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendInvoiceReadyNotification implements ShouldQueue
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

    public function failed(InvoiceReady $event, \Throwable $exception): void
    {
        Log::error(static::class.': gave up after '.$this->tries.' attempts', [
            'event' => InvoiceReady::class,
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
        ]);
    }
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

                if ($address === '') {
                    Log::warning('SendInvoiceReadyNotification: recipient has no notification address or email; nothing sent', [
                        'invoice_id' => $invoice->id,
                        'user_id' => $user->id,
                    ]);

                    continue;
                }

                if (isset($seen[$address])) {
                    continue;
                }

                $seen[$address] = true;

                $this->sendInvoiceReady($address, $invoice, $orgName, $isOrgUser, $user->usesSmsGateway());
            }
        };

        $deliver($orgUsers, true);        // staff first, so they win the dedupe
        $deliver($customerUsers, false);
    }

    private function sendInvoiceReady(string $address, $invoice, string $orgName, bool $isOrgUser, bool $viaSmsGateway = false): void
    {
        $url = $isOrgUser
            ? route('my.invoices.edit', ['invoice' => $invoice->id])
            : route('customer.invoices.show', ['invoice' => $invoice->id]);

        // A carrier gateway gets one short text, not the HTML email (TASK-468).
        if ($viaSmsGateway) {
            $this->mailSafely($address, new UserNotificationSms(OfficeSms::invoiceReady($invoice, $url, $isOrgUser)));

            return;
        }

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


