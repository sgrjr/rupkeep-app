<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersInvoiceExports;
use App\Models\Invoice;
use App\Services\InvoiceLineItems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
 */
class QuickBooksExportController extends Controller
{
    use FiltersInvoiceExports;

    /**
     * QuickBooks Online refuses an import above these, so a file over the limit
     * is a wasted round trip for the user rather than a partial success.
     */
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
    ];

    public function __invoke(Request $request): StreamedResponse|RedirectResponse
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

        $rows = $this->rows($invoices);

        if ($over = $this->overLimit($invoices, $rows)) {
            return back()->with('error', $over);
        }

        $filename = 'quickbooks-invoices-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // The column names from QuickBooks Online's own downloadable sample
            // file. Matching them means the import wizard's mapping step
            // pre-fills instead of asking the user to pair 15 columns by hand.
            fputcsv($handle, [
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
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Every CSV row for this export, invoice headers repeated down their lines.
     */
    private function rows(Collection $invoices): array
    {
        $rows = [];

        foreach ($invoices as $invoice) {
            $values = is_array($invoice->values) ? $invoice->values : [];
            $lines = InvoiceLineItems::forInvoice($invoice);

            // An invoice with no itemizable lines still has to bill. This
            // happens to summaries whose children were force-deleted, and to
            // very old invoices carrying a total and nothing else. Billing the
            // stored total as a single line is honest; skipping the invoice
            // would quietly drop revenue.
            if ($lines === []) {
                $total = (float) ($values['total'] ?? 0);

                if (round($total, 2) == 0.0) {
                    continue;
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

            $lateFees = $invoice->calculateLateFees();
            $dueDate = $lateFees['due_date'] ?? null;
            $invoiceDate = $invoice->created_at;

            $header = [
                $invoice->invoice_number,
                optional($invoice->customer)->name ?? '',
                $this->date($invoiceDate),
                $this->date($dueDate),
                $this->terms($invoiceDate, $dueDate),
                '',
                $this->memo($invoice, $values),
            ];

            foreach ($lines as $line) {
                $source = $line['invoice'];

                $rows[] = array_merge($header, [
                    self::ITEMS[$line['key']] ?? 'Pilot Car Escort',
                    $this->describe($invoice, $source, $line['description']),
                    $this->number((float) $line['quantity'], 2),
                    $this->number((float) $line['rate'], 2),
                    $this->number((float) $line['amount'], 2),
                    'N',
                    '',
                    $this->date($source->job?->scheduled_pickup_at),
                ]);
            }
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
        if (! $invoice->isSummary()) {
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

    /**
     * The message shown when the export is too big for QuickBooks to swallow,
     * or null when it fits.
     */
    private function overLimit(Collection $invoices, array $rows): ?string
    {
        if ($invoices->count() > self::MAX_INVOICES) {
            return __(
                'QuickBooks accepts :max invoices per import and this export has :count. Narrow the date range or pick a single customer, then export again.',
                ['max' => self::MAX_INVOICES, 'count' => $invoices->count()]
            );
        }

        if (count($rows) > self::MAX_ROWS) {
            return __(
                'QuickBooks accepts :max rows per import and this export has :count line items. Narrow the date range or pick a single customer, then export again.',
                ['max' => self::MAX_ROWS, 'count' => count($rows)]
            );
        }

        return null;
    }
}
