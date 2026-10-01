<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\PilotCarJob;

/**
 * Single-SMS bodies for the office-side notifications (TASK-468): invoice
 * ready, invoice flagged, log completed, maintenance due. These used to go
 * out as the full HTML email whatever the address was, so a manager whose
 * notification address is a carrier gateway got a multi-part text or an
 * attachment. Composed through {@see SmsMessage}, like {@see JobSms}.
 */
class OfficeSms
{
    public static function invoiceReady(Invoice $invoice, string $url, bool $forStaff): string
    {
        $total = Money::currency((float) str_replace(',', '', (string) ($invoice->values['total'] ?? 0)));

        return SmsMessage::make()
            ->fixed('Invoice ')
            ->flexible((string) $invoice->invoice_number)
            ->fixed($forStaff ? ' ready for review. ' : ' is ready. ')
            ->fixed('Total '.$total.'. ')
            ->fixed($forStaff ? 'Review/send: ' : 'View: ')
            ->url($url)
            ->build();
    }

    public static function invoiceFlagged(Invoice $invoice, ?string $by, string $url): string
    {
        return SmsMessage::make()
            ->fixed('Invoice ')
            ->flexible((string) $invoice->invoice_number)
            ->fixed(' flagged')
            ->fixed($by ? ' by ' : '. ')
            ->flexible($by)
            ->fixed($by ? '. ' : '')
            ->fixed('Review: ')
            ->url($url)
            ->build();
    }

    public static function logCompleted(PilotCarJob $job, string $driverName, string $url): string
    {
        return SmsMessage::make()
            ->flexible($driverName, 'Driver')
            ->fixed(' completed job ')
            ->flexible($job->job_no ?: ('#'.$job->id))
            ->fixed('. Ready to invoice: ')
            ->url($url)
            ->build();
    }

    public static function maintenanceDue(int $items, string $url): string
    {
        return SmsMessage::make()
            ->fixed(sprintf('Vehicle maintenance due: %d item%s. Manage: ', $items, $items === 1 ? '' : 's'))
            ->url($url)
            ->build();
    }
}
