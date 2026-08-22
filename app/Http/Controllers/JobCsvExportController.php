<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersInvoiceExports;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The full job register: one row per job, every column we hold.
 *
 * This is the descendant of the spreadsheet this business ran on before the
 * app -- a Google Form responses sheet, one row per driver log -- expanded with
 * everything the app has learned to compute since. It is the file you open in
 * Excel, hand to a bookkeeper, or feed back through our own importer. It is
 * NOT an accounting import; see QuickBooksExportController for that.
 *
 * Because a row means a job, the summary/child de-duplication runs the
 * opposite way here than it does in the QuickBooks export. A summary invoice
 * is not a job -- it is a cover sheet over several -- so where both a summary
 * and its children are in range, the children are the rows worth keeping and
 * the summary is dropped. Keeping both would double the revenue exactly as it
 * would over there (TASK-383).
 *
 * A summary is only dropped when *every* child it covers is present. If the
 * date range caught the summary but not the jobs under it, dropping it would
 * silently lose that revenue rather than merely duplicating it -- the worse of
 * the two failures -- so it stays, and rolls its children's figures up into
 * its own row so the detail still reaches the sheet.
 */
class JobCsvExportController extends Controller
{
    use FiltersInvoiceExports;

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizeExport($request);

        $invoices = $this->filteredInvoices($request, $this->exportFilters($request));
        $invoices->loadMissing(['children.job']);

        $present = $this->idLookup($invoices);

        $invoices = $invoices->reject(function (Invoice $invoice) use ($present) {
            if (! $invoice->isSummary()) {
                return false;
            }

            $children = $invoice->children;

            return $children->isNotEmpty()
                && $children->every(fn (Invoice $child) => isset($present[$child->id]));
        })->values();

        $filename = 'job-export-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            $header = [
                'Invoice Number',
                'Invoice Date',
                'Customer Name',
                'Customer Address',
                'Job Number',
                'Load Number',
                'Billable Miles',
                'Rate Code',
                'Rate Value',
                'Subtotal',
                'Expenses (Hotel)',
                'Expenses (Tolls)',
                'Expenses (Gas)',
                'Expenses (Wait Time)',
                'Expenses (Extra Charges)',
                'Extra Charges (Detail)',
                'Deadhead Count',
                'Deadhead Amount',
                'Mini Charge',
                'Total Amount',
                'Paid Status',
                'Payment Date',
                'Check Number',
                'Memo',
                'Summary Includes',
            ];

            fputcsv($handle, $header);

            foreach ($invoices as $invoice) {
                $values = is_array($invoice->values) ? $invoice->values : [];
                $job = $invoice->job;
                $customer = $invoice->customer;

                $customerAddress = '';
                if ($customer) {
                    $addressParts = array_filter([
                        $customer->street,
                        $customer->city,
                        $customer->state,
                        $customer->zip,
                    ]);
                    $customerAddress = implode(', ', $addressParts);
                }

                // Handle both flat and nested value structures.
                $totals = is_array($values['total'] ?? null) ? $values['total'] : [];

                $billableMiles = $values['billable_miles']
                    ?? $totals['billable_miles']
                    ?? ($job ? $job->miles?->billable : null)
                    ?? 0;

                // A summary that survived the reject above carries no expenses
                // of its own (TASK-379), so its row reports what its children
                // add up to. A single invoice reports itself.
                $children = $invoice->isSummary() ? $invoice->children : collect();
                $figures = $children->isNotEmpty()
                    ? $this->rollUpFigures($children)
                    : $this->invoiceFigures($invoice);
                $summaryDetail = $children->isNotEmpty()
                    ? $this->summaryDetail($children)
                    : '';

                $row = [
                    $invoice->invoice_number,
                    optional($invoice->created_at)->format('m/d/Y'),
                    optional($customer)->name ?? '',
                    $customerAddress,
                    optional($job)->job_no ?? '',
                    optional($job)->load_no ?? '',
                    number_format((float) $billableMiles, 1, '.', ''),
                    optional($job)->rate_code ?? '',
                    optional($job)->rate_value ?? '',
                    number_format($figures['subtotal'], 2, '.', ''),
                    number_format($figures['hotel'], 2, '.', ''),
                    number_format($figures['tolls'], 2, '.', ''),
                    number_format($figures['gas'], 2, '.', ''),
                    number_format($figures['wait_time'], 2, '.', ''),
                    number_format($figures['extra_charge'], 2, '.', ''),
                    $children->isNotEmpty()
                        ? $this->rolledUpExtraChargeDetail($children)
                        : $this->extraChargeDetail($values),
                    $figures['deadhead_count'],
                    number_format($figures['deadhead'], 2, '.', ''),
                    number_format($figures['mini'], 2, '.', ''),
                    number_format((float) ($totals['total'] ?? ($values['total'] ?? 0)), 2, '.', ''),
                    $invoice->paid_in_full ? 'Paid' : 'Unpaid',
                    $invoice->paid_in_full && $invoice->updated_at ? $invoice->updated_at->format('m/d/Y') : '',
                    optional($job)->check_no ?? '',
                    $values['notes'] ?? $values['memo'] ?? ($job ? $job->memo : '') ?? '',
                    $summaryDetail,
                ];

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * The expense and charge figures one invoice carries, read from wherever
     * its values blob happens to keep them (flat keys on older invoices, a
     * nested `expenses` / `total` array on newer ones).
     */
    private function invoiceFigures(Invoice $invoice): array
    {
        $values = is_array($invoice->values) ? $invoice->values : [];
        $totals = is_array($values['total'] ?? null) ? $values['total'] : [];
        $expenses = is_array($values['expenses'] ?? null) ? $values['expenses'] : [];
        $job = $invoice->job;

        return [
            'subtotal' => (float) ($totals['subtotal'] ?? $totals['base'] ?? ($values['subtotal'] ?? 0)),
            'hotel' => (float) ($expenses['hotel'] ?? $values['hotel'] ?? 0),
            'tolls' => (float) ($expenses['tolls'] ?? $values['tolls'] ?? 0),
            'gas' => (float) ($expenses['gas'] ?? $values['gas'] ?? 0),
            'wait_time' => (float) ($expenses['wait_time'] ?? $values['wait_time_hours'] ?? 0),
            'extra_charge' => (float) ($expenses['extra_charge'] ?? $values['extra_charge'] ?? 0),
            'deadhead_count' => (int) ($values['deadhead_count'] ?? $totals['deadhead_count'] ?? ($job && $job->is_deadhead ? 1 : 0)),
            'deadhead' => (float) ($totals['deadhead'] ?? $values['dead_head_charge'] ?? 0),
            'mini' => (float) ($totals['mini'] ?? $values['mini_addon_amount'] ?? $values['mini_cost'] ?? 0),
        ];
    }

    /**
     * The same figures for a summary, summed across the children it covers.
     */
    private function rollUpFigures(Collection $children): array
    {
        $rolled = array_fill_keys([
            'subtotal', 'hotel', 'tolls', 'gas', 'wait_time',
            'extra_charge', 'deadhead_count', 'deadhead', 'mini',
        ], 0);

        foreach ($children as $child) {
            foreach ($this->invoiceFigures($child) as $key => $value) {
                $rolled[$key] += $value;
            }
        }

        $rolled['deadhead_count'] = (int) $rolled['deadhead_count'];

        return $rolled;
    }

    /**
     * Which invoices a summary row stands for, and what each contributed.
     *
     * Same reasoning as extraChargeDetail() below: this CSV is one row per
     * invoice, so N children cannot each become a column. They ride in a single
     * text column instead.
     */
    private function summaryDetail(Collection $children): string
    {
        $parts = [];

        foreach ($children as $child) {
            $figures = $this->invoiceFigures($child);
            $values = is_array($child->values) ? $child->values : [];

            $expenseBits = [];
            foreach (['hotel' => 'Hotel', 'tolls' => 'Tolls', 'gas' => 'Gas', 'wait_time' => 'Wait'] as $key => $label) {
                if ($figures[$key] > 0) {
                    $expenseBits[] = $label.' '.number_format($figures[$key], 2, '.', '');
                }
            }

            $label = $child->invoice_number ?? ('#'.$child->id);
            $jobNo = $values['job_no'] ?? $child->job?->job_no;

            $line = $label.($jobNo ? ' ('.$jobNo.')' : '')
                .': '.number_format((float) ($values['total'] ?? 0), 2, '.', '');

            if ($expenseBits !== []) {
                $line .= ' — '.implode(', ', $expenseBits);
            }

            $parts[] = $line;
        }

        return implode('; ', $parts);
    }

    /**
     * The named extra charges of every child a summary covers, each prefixed
     * with the child it came from.
     */
    private function rolledUpExtraChargeDetail(Collection $children): string
    {
        $parts = [];

        foreach ($children as $child) {
            $detail = $this->extraChargeDetail(is_array($child->values) ? $child->values : []);

            if ($detail !== '') {
                $parts[] = ($child->invoice_number ?? ('#'.$child->id)).': '.$detail;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * A readable breakdown of the invoice's named extra charges (TASK-378).
     *
     * This CSV is one row per invoice, so N charges cannot each become a column
     * without dynamic headers. They ride in a single adjacent text column
     * instead: the scalar "Expenses (Extra Charges)" stays the authoritative
     * figure that totals against, and this one says what it was made of.
     *
     * Empty for invoices issued before TASK-330, which recorded only the total
     * and no itemization -- an empty cell is honest there, an invented one
     * would not be.
     */
    private function extraChargeDetail(array $values): string
    {
        $lines = $values['extra_charges'] ?? null;

        if (! is_array($lines) || $lines === []) {
            return '';
        }

        $parts = [];

        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $parts[] = $description.' $'.number_format((float) ($line['amount'] ?? 0), 2);
        }

        return implode('; ', $parts);
    }
}
