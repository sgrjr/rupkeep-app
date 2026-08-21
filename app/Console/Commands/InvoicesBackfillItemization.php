<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * TASK-365 backfill. Invoices store a `values` snapshot taken when they were
 * generated. Snapshots written before the itemization fix are missing the
 * per-charge dollar amounts the invoice template reads, so wait time, extra
 * stops and mileage render as $0.00 even though their money is inside `total`.
 *
 * This fills those keys in from the parent job's canonical math. It deliberately
 * will NOT touch an invoice whose recomputed total differs from the stored one
 * — a changed total means pricing moved (or the logs did) since the invoice was
 * cut, and silently restating an issued invoice's amount is not a backfill.
 * Those are listed instead so a human can regenerate them intentionally.
 */
class InvoicesBackfillItemization extends Command
{
    protected $signature = 'invoices:backfill-itemization
        {--write : Apply the changes (default is a dry run)}
        {--tolerance=0.01 : Max allowed drift between stored and recomputed total}';

    protected $description = 'Backfill itemized charge amounts (wait time, extra stops, mileage) onto existing invoice snapshots so invoice lines stop rendering $0.00.';

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $tolerance = (float) $this->option('tolerance');

        $summary = ['updated' => 0, 'already_itemized' => 0, 'skipped_total_drift' => 0, 'skipped_no_job' => 0];
        $drifted = [];

        Invoice::with('job')->whereNull('parent_invoice_id')->chunkById(100, function ($invoices) use ($write, $tolerance, &$summary, &$drifted) {
            foreach ($invoices as $invoice) {
                if ($invoice->isSummary()) {
                    continue; // Summary invoices itemize from their children, not from job math.
                }

                $values = is_array($invoice->values) ? $invoice->values : [];

                if (array_key_exists('cost_of_wait_time', $values) && array_key_exists('cost_for_mileage', $values)) {
                    $summary['already_itemized']++;
                    continue;
                }

                $job = $invoice->job;

                if (! $job) {
                    $summary['skipped_no_job']++;
                    continue;
                }

                $fresh = $job->invoiceValues()['values'];

                $storedTotal = (float) str_replace(',', '', (string) ($values['total'] ?? 0));
                $freshTotal = (float) ($fresh['total'] ?? 0);

                if (abs($storedTotal - $freshTotal) > $tolerance) {
                    $summary['skipped_total_drift']++;
                    $drifted[] = [$invoice->invoice_number ?? $invoice->id, number_format($storedTotal, 2), number_format($freshTotal, 2)];
                    continue;
                }

                foreach (['cost_of_extra_stop', 'cost_of_wait_time', 'cost_for_mileage', 'wait_time_rate', 'wait_time_billable_hours'] as $key) {
                    $values[$key] = (float) ($fresh[$key] ?? 0);
                }

                if ($write) {
                    $invoice->values = $values;
                    $invoice->save();
                }

                $summary['updated']++;
            }
        });

        if ($drifted) {
            $this->newLine();
            $this->warn('Skipped — stored total no longer matches a fresh calculation. Regenerate these from the job page if the new figure is correct:');
            $this->table(['Invoice', 'Stored total', 'Recomputed'], $drifted);
        }

        $this->newLine();
        foreach ($summary as $key => $count) {
            $this->line(sprintf('  %-22s %d', $key . ':', $count));
        }

        if (! $write) {
            $this->newLine();
            $this->info('Dry run — nothing written. Re-run with --write to apply.');
        }

        return self::SUCCESS;
    }
}
