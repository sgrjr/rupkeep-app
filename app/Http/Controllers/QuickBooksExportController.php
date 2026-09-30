<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersInvoiceExports;
use App\Models\Invoice;
use App\Services\InvoiceLineItems;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * An accounts-receivable feed shaped for QuickBooks Online's own invoice
 * importer, as opposed to the job register in JobCsvExportController.
 *
 * The difference that matters is the shape. Our job CSV is one row per job with
 * every figure in its own column, which is a report -- QuickBooks has no idea
 * what "Expenses (Wait Time)" is. QuickBooks models an invoice as a header plus
 * N line items, and its CSV importer expresses that by repeating the invoice
 * number down consecutive rows: same *InvoiceNo, one row per line. So that is
 * what this writes.
 *
 * The lines come from InvoiceLineItems -- the same code that prints the
 * customer's invoice. That is deliberate and load-bearing: the amounts on a
 * QuickBooks invoice will equal the amounts on the paper one line for line,
 * because they are literally the same list. Deriving a second breakdown here
 * would eventually disagree with the document the customer is holding.
 *
 * A summary invoice bills as ONE QuickBooks invoice whose lines are its
 * children's lines, each labelled with the job it came from. That preserves the
 * detail TASK-383 had to flatten into a text column, without double-counting:
 * the summary's total is the sum of its children, and here so are its lines.
 * Where an admin set the summary's total by hand, an Adjustment line carries
 * the difference; where a late fee was applied, a Late Fee line carries it;
 * so the QuickBooks total equals the paper one in both cases (TASK-450).
 *
 * QuickBooks accepts at most 100 invoices and 1,000 rows per file. Rather
 * than refuse a larger export, it is split into numbered files inside one
 * zip, each within both limits, and imported one after another.
 */
class QuickBooksExportController extends Controller
{
    use FiltersInvoiceExports;

    private const MAX_INVOICES = 100;
    private const MAX_ROWS = 1000;

    /**
     * Our line keys mapped to the products/services QuickBooks will post to.
     *
     * These become items in the customer's QuickBooks chart on first import, so
     * they are deliberately few and stable. The specific, wordy text (which
     * expense, which job, how many free wait hours) rides in ItemDescription,
     * which is free text and posts to nothing.
     */
    private const ITEMS = [
        'pilot_car_service' => 'Pilot Car Escort',
        'wait_time' => 'Wait Time',
        'extra_stops' => 'Extra Stop',
        'dead_head' => 'Deadhead',
        'tolls' => 'Tolls',
        'hotel' => 'Lodging',
        'extra_charge' => 'Extra Charge',
        'mileage' => 'Mileage',
        'mini_addon' => 'Mini Add-On',
        'adjustment' => 'Adjustment',
        'late_fee' => 'Late Fee',
    ];

    private const HEADER = [
        '*InvoiceNo',
        '*Customer',
        '*InvoiceDate',
        '*DueDate',
        'Terms',
        'Location',
        'Memo',
        'Item(Product/Service)',
        'ItemDescription',
        'ItemQuantity',
        'ItemRate',
        '*ItemAmount',
        'Taxable',
        'TaxRate',
        'Service Date',
    ];

    public function __invoke(Request $request): StreamedResponse|BinaryFileResponse
    {
        $this->authorizeExport($request);

        $invoices = $this->filteredInvoices($request, $this->exportFilters($request));
        $invoices->loadMissing(['children.job']);

        // TASK-383: a summary's total IS the sum of its children's totals, so
        // billing both doubles the revenue for those jobs. The summary is the
        // document the customer received and paid against, so that is the one
        // QuickBooks should carry -- and its children's lines ride inside it.
        //
        // A child is dropped only when its summary is present in this same
        // export. If the range caught the children but not the summary cut
        // later, dropping them would lose the revenue rather than duplicate it,
        // which is the worse failure, so those children bill on their own.
        $present = $this->idLookup($invoices);

        $invoices = $invoices->reject(
            fn (Invoice $invoice) => $invoice->parent_invoice_id !== null
                && isset($present[$invoice->parent_invoice_id])
        )->values();

        $files = $this->files($invoices);
        $stamp = now()->format('Ymd-His');

        if (count($files) <= 1) {
            $filename = 'quickbooks-invoices-'.$stamp.'.csv';

            return response()->streamDownload(function () use ($files) {
                $handle = fopen('php://output', 'w');
                $this->writeCsv($handle, $files[0] ?? []);
                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        return $this->zipped($files, $stamp);
    }

    /**
     * The export split into QuickBooks-sized files: each holds at most 100
     * invoices and 1,000 rows, and an invoice is never split across two.
     *
     * @return array<int, array<int, array>> files, each a list of CSV rows
     */
    private function files(Collection $invoices): array
    {
        $files = [];
        $current = [];
        $currentInvoices = 0;

        foreach ($invoices as $invoice) {
            $rows = $this->rowsFor($invoice);

            if ($rows === []) {
                continue;
            }

            $wouldOverflow = $currentInvoices + 1 > self::MAX_INVOICES
                || count($current) + count($rows) > self::MAX_ROWS;

            if ($current !== [] && $wouldOverflow) {
                $files[] = $current;
                $current = [];
                $currentInvoices = 0;
            }

            array_push($current, ...$rows);
            $currentInvoices++;
        }

        if ($current !== []) {
            $files[] = $current;
        }

        return $files;
    }

    /**
     * One zip, one numbered CSV per QuickBooks import inside it.
     */
    private function zipped(array $files, string $stamp): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'qbo');
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            abort(500, __('The export could not be packaged.'));
        }

        $total = count($files);

        foreach ($files as $index => $rows) {
            $handle = fopen('php://temp', 'r+');
            $this->writeCsv($handle, $rows);
            rewind($handle);
            $zip->addFromString(
                sprintf('quickbooks-invoices-%s-part%02d-of-%02d.csv', $stamp, $index + 1, $total),
                stream_get_contents($handle)
            );
            fclose($handle);
        }

        $zip->close();

        return response()
            ->download($path, 'quickbooks-invoices-'.$stamp.'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * @param resource $handle
     */
    private function writeCsv($handle, array $rows): void
    {
        // The column names from QuickBooks Online's own downloadable sample
        // file. Matching them means the import wizard's mapping step
        // pre-fills instead of asking the user to pair 15 columns by hand.
        fputcsv($handle, self::HEADER);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
    }

    /**
     * Every CSV row for one invoice: its header repeated down its lines.
     */
    private function rowsFor(Invoice $invoice): array
    {
        $values = is_array($invoice->values) ? $invoice->values : [];
        $lines = InvoiceLineItems::forInvoice($invoice);
        $total = (float) str_replace(',', '', (string) ($values['total'] ?? 0));

        // An invoice with no itemizable lines still has to bill. This
        // happens to summaries whose children were force-deleted, and to
        // very old invoices carrying a total and nothing else. Billing the
        // stored total as a single line is honest; skipping the invoice
        // would quietly drop revenue.
        if ($lines === []) {
            if (round($total, 2) == 0.0) {
                return [];
            }

            $lines = [[
                'invoice' => $invoice,
                'key' => 'pilot_car_service',
                'description' => __('Pilot Car Service'),
                'quantity' => 1,
                'rate' => $total,
                'amount' => $total,
            ]];
        }

        // A summary's children reconcile to their own totals, not to the
        // summary's: when an admin set the summary's figure by hand (a
        // discount, usually) the difference is a line of its own, so the
        // QuickBooks invoice totals what the paper one does.
        if ($invoice->isSummary()) {
            $linesTotal = array_sum(array_map(fn ($line) => (float) $line['amount'], $lines));
            $difference = round($total - $linesTotal, 2);

            if (abs($difference) >= 0.005) {
                $lines[] = [
                    'invoice' => $invoice,
                    'key' => 'adjustment',
                    'description' => $difference < 0 ? __('Summary discount') : __('Summary adjustment'),
                    'quantity' => 1,
                    'rate' => $difference,
                    'amount' => $difference,
                ];
            }
        }

        // A late fee that was actually applied is money the customer was
        // billed (TASK-443 keeps it beside the total rather than inside it).
        $lateFees = $invoice->calculateLateFees();
        $appliedFee = (float) ($lateFees['applied_late_fee_amount'] ?? 0);

        if ($appliedFee > 0) {
            $appliedAt = $lateFees['applied_at'] ?? null;
            $lines[] = [
                'invoice' => $invoice,
                'key' => 'late_fee',
                'description' => $appliedAt
                    ? __('Late fee applied :date', ['date' => $this->date($appliedAt)])
                    : __('Late fee'),
                'quantity' => 1,
                'rate' => $appliedFee,
                'amount' => $appliedFee,
            ];
        }

        $dueDate = $lateFees['due_date'] ?? null;
        $invoiceDate = $invoice->created_at;

        $header = [
            $invoice->invoice_number,
            $this->csvText(optional($invoice->customer)->name ?? ''),
            $this->date($invoiceDate),
            $this->date($dueDate),
            $this->terms($invoiceDate, $dueDate),
            '',
            $this->csvText($this->memo($invoice, $values)),
        ];

        $rows = [];

        foreach ($lines as $line) {
            $source = $line['invoice'];

            $rows[] = array_merge($header, [
                self::ITEMS[$line['key']] ?? 'Pilot Car Escort',
                $this->csvText($this->describe($invoice, $source, $line['description'])),
                $this->number((float) $line['quantity'], 2),
                $this->number((float) $line['rate'], 2),
                $this->number((float) $line['amount'], 2),
                'N',
                '',
                $this->date($source->job?->scheduled_pickup_at),
            ]);
        }

        return $rows;
    }

    /**
     * The line's own text, told which job it belongs to when the invoice covers
     * more than one. On a single-job invoice the job number is already in the
     * memo, so repeating it on every line is noise.
     *
     * Separators here are plain ASCII on purpose. This file is parsed by
     * QuickBooks rather than read by a person, and it carries no byte-order
     * mark (one would corrupt the first header name and break the importer's
     * auto-mapping), so an em dash is a gamble for no gain.
     */
    private function describe(Invoice $invoice, Invoice $source, string $description): string
    {
        if (! $invoice->isSummary() || $source->is($invoice)) {
            return $description;
        }

        $values = is_array($source->values) ? $source->values : [];
        $jobNo = $values['job_no'] ?? $source->job?->job_no;

        return $jobNo ? $jobNo.' - '.$description : $description;
    }

    /**
     * What the bookkeeper reads on the invoice itself: which job (or jobs) it
     * covers, then whatever note was written for the customer.
     */
    private function memo(Invoice $invoice, array $values): string
    {
        $parts = [];

        if ($invoice->isSummary()) {
            $jobNos = $invoice->children
                ->map(fn (Invoice $child) => (is_array($child->values) ? $child->values : [])['job_no']
                    ?? $child->job?->job_no)
                ->filter()
                ->all();

            if ($jobNos !== []) {
                $parts[] = __('Jobs: :list', ['list' => implode(', ', $jobNos)]);
            }
        } elseif ($jobNo = ($values['job_no'] ?? $invoice->job?->job_no)) {
            $parts[] = __('Job :no', ['no' => $jobNo]);
        }

        $note = trim((string) ($values['notes'] ?? $values['memo'] ?? $invoice->job?->memo ?? ''));

        if ($note !== '') {
            $parts[] = $note;
        }

        return implode(' - ', $parts);
    }

    /**
     * "Net 30" and friends, derived from the grace period the invoice was
     * actually issued under rather than assumed.
     */
    private function terms(?Carbon $invoiceDate, ?Carbon $dueDate): string
    {
        if (! $invoiceDate || ! $dueDate) {
            return '';
        }

        $days = (int) round($invoiceDate->diffInDays($dueDate));

        return $days > 0 ? 'Net '.$days : '';
    }

    private function date($value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('m/d/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Plain decimals, no thousands separator -- a comma inside a CSV cell is
     * quoted correctly by fputcsv but read as text by QuickBooks.
     */
    private function number(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }
}
