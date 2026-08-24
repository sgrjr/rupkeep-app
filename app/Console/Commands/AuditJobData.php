<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\PilotCarJob;
use App\Models\UserLog;
use App\Services\InvoiceLineItems;
use Illuminate\Console\Command;

/**
 * Read-only integrity sweep over jobs, logs and invoices.
 *
 * The job page can already dump one job's record ("Log job JSON to console",
 * TASK-368), which answers "why is THIS figure wrong". This answers the other
 * question -- "is anything wrong at all" -- by asserting the invariants that
 * must hold no matter how the data got there, across every row at once.
 *
 * Nothing here is a style opinion. Every finding is a statement that two
 * numbers which must agree do not, so a clean run is meaningful evidence and a
 * dirty one always points at a real defect or a real data problem.
 */
class AuditJobData extends Command
{
    protected $signature = 'jobs:audit
        {organization_id? : Limit to one organization}
        {--tolerance=0.01 : Money differences smaller than this are rounding, not drift}';

    protected $description = 'Check that logs, mileage and invoice math reconcile (read-only).';

    /** @var array<int, array{severity: string, subject: string, finding: string}> */
    private array $findings = [];

    /** Context, not a finding: jobs still mid-lifecycle. */
    private int $uninvoicedJobs = 0;

    public function handle(): int
    {
        $organizationId = $this->argument('organization_id');
        $tolerance = (float) $this->option('tolerance');

        $this->auditLogs($organizationId);
        $this->auditInvoices($organizationId, $tolerance);

        $this->reportContext();

        if ($this->findings === []) {
            $this->info('All checks passed. Logs, mileage and invoice math reconcile.');

            return self::SUCCESS;
        }

        $this->table(
            ['Severity', 'Subject', 'Finding'],
            array_map(fn ($f) => [$f['severity'], $f['subject'], $f['finding']], $this->findings)
        );

        $errors = count(array_filter($this->findings, fn ($f) => $f['severity'] === 'ERROR'));

        $this->newLine();
        $this->warn(sprintf(
            '%d finding(s): %d error, %d warning.',
            count($this->findings),
            $errors,
            count($this->findings) - $errors
        ));

        // Warnings are facts about messy data; errors are broken invariants.
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function auditLogs(?string $organizationId): void
    {
        UserLog::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                foreach ($logs as $log) {
                    $subject = "log {$log->id}";

                    // The four odometer readings must describe one forward trip.
                    // Out of order, every derived segment is meaningless.
                    $anyReading = $log->start_mileage !== null || $log->end_mileage !== null
                        || $log->start_job_mileage !== null || $log->end_job_mileage !== null;

                    if ($anyReading && ! $log->hasOrderedMileageReadings()) {
                        $this->warning($subject, sprintf(
                            'Odometer readings incomplete or out of order (%s -> %s -> %s -> %s)',
                            $log->start_mileage ?? '?',
                            $log->start_job_mileage ?? '?',
                            $log->end_job_mileage ?? '?',
                            $log->end_mileage ?? '?'
                        ));
                    }

                    $driven = (float) ($log->dead_head_driven ?? 0);
                    $billed = (float) ($log->dead_head_billed ?? 0);

                    // Billing deadhead nobody recorded driving is incoherent:
                    // the charge has no measurement behind it.
                    if ($billed > 0 && $driven <= 0) {
                        $this->error_($subject, "Bills {$billed} deadhead miles but none are recorded as driven");
                    }

                    // The published free allowance is a hard ceiling. Passing it
                    // means something wrote around the form's guard.
                    $ceiling = $log->deadHeadBillingCeiling();

                    if ($billed > $ceiling + 0.001) {
                        $this->error_($subject, sprintf(
                            'Bills %s deadhead miles, above the %s ceiling (%s driven, first %s free)',
                            $billed,
                            $ceiling,
                            $driven,
                            $log->deadHeadFreeMiles()
                        ));
                    }

                    // Deadhead is carved out of the non-billable residual, so it
                    // cannot exceed the miles that were not spent under load.
                    if ($driven > 0 && $log->hasOrderedMileageReadings()) {
                        $nonBillable = (float) $log->approach_miles + (float) $log->release_miles;

                        if ($driven > $nonBillable + 0.001) {
                            $this->warning($subject, sprintf(
                                'Records %s deadhead miles but only %s were driven outside the job itself',
                                $driven,
                                $nonBillable
                            ));
                        }
                    }
                }
            });
    }

    private function auditInvoices(?string $organizationId, float $tolerance): void
    {
        Invoice::query()
            ->with('job')
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($tolerance) {
                foreach ($invoices as $invoice) {
                    $subject = 'invoice ' . ($invoice->invoice_number ?? $invoice->id);
                    $values = is_array($invoice->values) ? $invoice->values : [];
                    $storedTotal = (float) str_replace(',', '', (string) ($values['total'] ?? 0));

                    if ($invoice->isSummary()) {
                        // A summary is a cover sheet: its total is the sum of its
                        // children, and it itemizes nothing of its own.
                        $childTotal = $invoice->children->sum(
                            fn (Invoice $child) => (float) str_replace(',', '', (string) data_get($child->values ?? [], 'total', 0))
                        );

                        if (! array_key_exists('total_override', $values)
                            && abs($childTotal - $storedTotal) > $tolerance) {
                            $this->error_($subject, sprintf(
                                'Summary total %s does not match its children (%s)',
                                number_format($storedTotal, 2),
                                number_format($childTotal, 2)
                            ));
                        }

                        continue;
                    }

                    // Every dollar in the total must appear on a line, or the
                    // customer is billed money the invoice never explains.
                    $lineTotal = array_sum(array_map(
                        fn ($line) => (float) $line['amount'],
                        InvoiceLineItems::build($values)
                    ));

                    if (abs($lineTotal - $storedTotal) > $tolerance) {
                        $this->error_($subject, sprintf(
                            'Line items sum to %s but the total is %s',
                            number_format($lineTotal, 2),
                            number_format($storedTotal, 2)
                        ));
                    }

                    // Drift is not automatically wrong -- an invoice is a
                    // snapshot and the job may have moved since -- but it is
                    // always worth knowing before sending it.
                    if ($invoice->job) {
                        $freshTotal = (float) ($invoice->job->invoiceValues()['values']['total'] ?? 0);

                        if (abs($freshTotal - $storedTotal) > $tolerance) {
                            $this->warning($subject, sprintf(
                                'Snapshot says %s, the job now computes %s',
                                number_format($storedTotal, 2),
                                number_format($freshTotal, 2)
                            ));
                        }
                    }
                }
            });

        // A job carrying logs but no invoice is the normal mid-lifecycle state,
        // so this is reported as context rather than as a finding. Counting it
        // against a run would mean healthy data never produces a clean result,
        // and a check that always fires is a check nobody reads.
        $this->uninvoicedJobs = PilotCarJob::query()
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->has('logs')
            ->doesntHave('invoices')
            ->count();
    }

    private function reportContext(): void
    {
        if ($this->uninvoicedJobs > 0) {
            $this->line("  {$this->uninvoicedJobs} job(s) have logs but no invoice yet (normal mid-lifecycle).");
        }
    }

    private function error_(string $subject, string $finding): void
    {
        $this->findings[] = ['severity' => 'ERROR', 'subject' => $subject, 'finding' => $finding];
    }

    private function warning(string $subject, string $finding): void
    {
        $this->findings[] = ['severity' => 'warning', 'subject' => $subject, 'finding' => $finding];
    }
}
