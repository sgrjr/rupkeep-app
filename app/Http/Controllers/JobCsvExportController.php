<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FiltersInvoiceExports;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
 *
 * The money columns reconcile (TASK-450): Subtotal + Hotel + Tolls + Wait
 * Time + Extra Charges + Deadhead + Mini = Total Amount, on every row. Gas is
 * the drivers' own fuel from the logs and is not billed, so it stays outside
 * that sum.
 */
class JobCsvExportController extends Controller
{
    use FiltersInvoiceExports;

    public function __invoke(Request $request): StreamedResponse
    {
        $this->authorizeExport($request);

        $invoices = $this->filteredInvoices($request, $this->exportFilters($request));
        $invoices->loadMissing(['job.logs', 'children.job.logs']);

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
                    ? $this->rollUpFigures($children, $invoice)
                    : $this->invoiceFigures($invoice);
                $summaryDetail = $children->isNotEmpty()
                    ? $this->summaryDetail($children)
                    : '';

                $payments = $this->payments($values);

                $row = [
                    $invoice->invoice_number,
                    optional($invoice->created_at)->format('m/d/Y'),
                    $this->csvText(optional($customer)->name ?? ''),
                    $this->csvText($customerAddress),
                    $this->csvText($values['job_no'] ?? optional($job)->job_no ?? ''),
                    $this->csvText($values['load_no'] ?? optional($job)->load_no ?? ''),
                    number_format((float) $billableMiles, 1, '.', ''),
                    // The rate the invoice was cut at, not whatever the job
                    // says today (TASK-450).
                    $this->csvText($values['rate_code'] ?? optional($job)->rate_code ?? ''),
                    $values['rate_value'] ?? optional($job)->rate_value ?? '',
                    number_format($figures['subtotal'], 2, '.', ''),
                    number_format($figures['hotel'], 2, '.', ''),
                    number_format($figures['tolls'], 2, '.', ''),
                    number_format($figures['gas'], 2, '.', ''),
                    number_format($figures['wait_time'], 2, '.', ''),
                    number_format($figures['extra_charge'], 2, '.', ''),
                    $this->csvText($children->isNotEmpty()
                        ? $this->rolledUpExtraChargeDetail($children)
                        : $this->extraChargeDetail($values)),
                    $figures['deadhead_count'],
                    number_format($figures['deadhead'], 2, '.', ''),
                    number_format($figures['mini'], 2, '.', ''),
                    number_format($figures['total'], 2, '.', ''),
                    $this->paidStatus($invoice, $payments),
                    $this->paymentDate($invoice, $payments),
                    $this->csvText(optional($job)->check_no ?? ''),
                    $this->csvText($values['notes'] ?? $values['memo'] ?? ($job ? $job->memo : '') ?? ''),
                    $this->csvText($summaryDetail),
                ];

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * The expense and charge figures one invoice carries, in dollars.
     *
     * Every charge is read from the key invoiceValues() actually writes
     * (`cost_of_wait_time`, `dead_head_charge`, ...). Before TASK-450 the wait
     * column read `wait_time_hours`, so three hours billed at $90 exported as
     * 3.00, and Subtotal read keys nothing ever wrote, so it was always 0.
     * Subtotal is now whatever is left of the total once every column beside
     * it is taken out -- the same definition InvoiceLineItems gives the
     * "Pilot Car Service" line -- so the row adds up.
     *
     * The nested `expenses` / `total` reads are kept for the handful of very
     * old snapshots that were written in that shape.
     */
    private function invoiceFigures(Invoice $invoice): array
    {
        $values = is_array($invoice->values) ? $invoice->values : [];
        $totals = is_array($values['total'] ?? null) ? $values['total'] : [];
        $expenses = is_array($values['expenses'] ?? null) ? $values['expenses'] : [];
        $money = fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));

        $waitTime = isset($values['cost_of_wait_time'])
            ? $money($values['cost_of_wait_time'])
            : (isset($expenses['wait_time'])
                ? $money($expenses['wait_time'])
                : (isset($values['wait_time_hours'], $values['wait_time_rate'])
                    ? (float) $values['wait_time_hours'] * (float) $values['wait_time_rate']
                    : 0.0));

        $extraStops = isset($values['cost_of_extra_stop'], $values['extra_load_stops_count'])
            ? (float) $values['extra_load_stops_count'] * $money($values['cost_of_extra_stop'])
            : 0.0;

        $figures = [
            'hotel' => $money($expenses['hotel'] ?? $values['hotel'] ?? 0),
            'tolls' => $money($expenses['tolls'] ?? $values['tolls'] ?? 0),
            // Fuel is the drivers' own expense from their logs, never billed.
            // The snapshot rarely carries it, so the logs are the source.
            'gas' => isset($expenses['gas']) || isset($values['gas'])
                ? $money($expenses['gas'] ?? $values['gas'])
                : (float) ($invoice->job?->logs->sum(fn ($log) => (float) ($log->gas ?? 0)) ?? 0),
            'wait_time' => $waitTime,
            'extra_charge' => $money($expenses['extra_charge'] ?? $values['extra_charge'] ?? 0),
            // `deadhead_count` is not a key invoiceValues() has ever emitted, and
            // the last fallback read is_deadhead off a JOB, where that column has
            // never existed - so this column exported 0 for every row. The trip
            // count actually lives under `dead_head` (TASK-354).
            'deadhead_count' => (int) ($values['dead_head'] ?? $values['deadhead_count'] ?? $totals['deadhead_count'] ?? 0),
            'deadhead' => $money($totals['deadhead'] ?? $values['dead_head_charge'] ?? 0),
            'mini' => $money($totals['mini'] ?? $values['mini_addon_amount'] ?? $values['mini_cost'] ?? 0),
            'total' => $money($totals['total'] ?? (is_array($values['total'] ?? null) ? 0 : ($values['total'] ?? 0))),
        ];

        // Extra stops have no column of their own, so they stay inside the
        // subtotal along with the rate's own charge and the mileage.
        $figures['subtotal'] = round($figures['total'] - (
            $figures['hotel'] + $figures['tolls'] + $figures['wait_time']
            + $figures['extra_charge'] + $figures['deadhead'] + $figures['mini']
        ), 2);
        $figures['extra_stops'] = $extraStops;

        return $figures;
    }

    /**
     * The same figures for a summary, summed across the children it covers.
     */
    private function rollUpFigures(Collection $children, Invoice $summary): array
    {
        $rolled = array_fill_keys([
            'subtotal', 'hotel', 'tolls', 'gas', 'wait_time', 'extra_charge',
            'deadhead_count', 'deadhead', 'mini', 'total', 'extra_stops',
        ], 0);

        foreach ($children as $child) {
            foreach ($this->invoiceFigures($child) as $key => $value) {
                $rolled[$key] += $value;
            }
        }

        $rolled['deadhead_count'] = (int) $rolled['deadhead_count'];

        // The total is the summary's own: it is what the customer was billed,
        // and an admin may have set it by hand (SummaryInvoiceValues override).
        // Any difference from the children's sum lands in Subtotal, so the
        // row still adds up.
        $values = is_array($summary->values) ? $summary->values : [];
        $rolled['total'] = (float) str_replace(',', '', (string) ($values['total'] ?? $rolled['total']));
        $rolled['subtotal'] = round($rolled['total'] - (
            $rolled['hotel'] + $rolled['tolls'] + $rolled['wait_time']
            + $rolled['extra_charge'] + $rolled['deadhead'] + $rolled['mini']
        ), 2);

        return $rolled;
    }

    /** The payments recorded on the invoice (InvoicePaymentForm), oldest first. */
    private function payments(array $values): array
    {
        $payments = $values['payments'] ?? [];

        return is_array($payments) ? array_values(array_filter($payments, 'is_array')) : [];
    }

    /**
     * Paid, Partial or Unpaid. A partly paid invoice used to export as
     * "Unpaid", which is what a bookkeeper would chase (TASK-450).
     */
    private function paidStatus(Invoice $invoice, array $payments): string
    {
        if ($invoice->paid_in_full) {
            return 'Paid';
        }

        $paid = array_sum(array_map(fn ($p) => (float) ($p['amount'] ?? 0), $payments));

        return $paid > 0 ? 'Partial' : 'Unpaid';
    }

    /**
     * The date of the last payment recorded, else the date the invoice was
     * marked paid. `updated_at` used to stand in for both, and it moves on
     * every edit.
     */
    private function paymentDate(Invoice $invoice, array $payments): string
    {
        $dates = array_filter(array_map(fn ($p) => $p['payment_date'] ?? null, $payments));

        if ($dates !== []) {
            try {
                return Carbon::parse(max($dates))->format('m/d/Y');
            } catch (\Throwable) {
                // fall through to the invoice's own stamp
            }
        }

        if ($invoice->paid_in_full) {
            $stamp = $invoice->paid_at ?? $invoice->updated_at;

            return $stamp ? $stamp->format('m/d/Y') : '';
        }

        return '';
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
