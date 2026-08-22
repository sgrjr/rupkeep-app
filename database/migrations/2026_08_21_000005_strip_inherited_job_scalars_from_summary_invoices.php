<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TASK-379: clear the per-job scalars summary invoices inherited from whichever
 * child happened to be first.
 *
 * MyInvoicesController::buildSummaryValues() used to seed itself from the first
 * child invoice's entire values array, so every summary ever created carries
 * that one child's hotel, tolls, extra_charge, rate_code, job_no and so on as
 * dead keys. Nothing prints them on the summary document, but the QuickBooks
 * export reads them straight off `values` and wrote them out as though they
 * belonged to the whole summary.
 *
 * The builder is fixed going forward; this brings the invoices already in the
 * database in line with it, keeping only what a summary legitimately holds:
 * its own totals, its child rows, the bill-from/bill-to and chrome, and
 * anything recorded against the summary itself afterwards (payments, applied
 * late fees, notes an admin typed).
 */
return new class extends Migration
{
    /**
     * Keys a summary invoice is allowed to keep. Everything else on a summary
     * came from a child and describes a single job.
     */
    private const KEEP = [
        // Presentation, shared by every child (same customer, same org).
        'bill_from',
        'bill_to',
        'logo',
        'footer',
        'title',
        // The summary's own figures.
        'total',
        'billable_miles',
        'summary_items',
        'child_invoice_ids',
        // Recorded against the summary after it was created -- never inherited.
        'payments',
        'late_fees',
        'notes',
    ];

    public function up(): void
    {
        DB::table('invoices')
            ->where('invoice_type', 'summary')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $values = json_decode($invoice->values ?? '', true);

                    if (! is_array($values)) {
                        continue;
                    }

                    $kept = array_intersect_key($values, array_flip(self::KEEP));

                    if ($kept === $values) {
                        continue;
                    }

                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update(['values' => json_encode($kept)]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible: the discarded keys were a copy of one child invoice's
        // values, and that child still holds the authoritative copy. Nothing to
        // restore them from, and nothing that wants them back.
    }
};
