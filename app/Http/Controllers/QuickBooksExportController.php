<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuickBooksExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isManager() && ! $user->isSuper()) {
            abort(403);
        }

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
            'paid' => ['nullable', 'in:yes,no'],
        ]);
        
        // Normalize empty strings to null to ensure they're not treated as filters
        $data['from'] = !empty($data['from']) ? $data['from'] : null;
        $data['to'] = !empty($data['to']) ? $data['to'] : null;
        $data['customer_id'] = !empty($data['customer_id']) ? $data['customer_id'] : null;
        $data['paid'] = !empty($data['paid']) ? $data['paid'] : null;

        $query = Invoice::query()
            ->with(['customer', 'job'])
            ->where('organization_id', $user->organization_id)
            ->orderByDesc('created_at');

        // Apply filters only if provided - if no filters, export ALL invoices
        if (! empty($data['from'])) {
            $query->whereDate('created_at', '>=', Carbon::parse($data['from']));
        }

        if (! empty($data['to'])) {
            $query->whereDate('created_at', '<=', Carbon::parse($data['to']));
        }

        if (! empty($data['customer_id'])) {
            $query->where('customer_id', $data['customer_id']);
        }

        if (! empty($data['paid'])) {
            $query->where('paid_in_full', $data['paid'] === 'yes');
        }

        // Explicitly get all results - no limit, no pagination when no filters are provided
        // This ensures ALL invoices are exported when filters are empty
        $invoices = $query->limit(null)->get();

        // TASK-383: a summary invoice's total IS the sum of its children's totals,
        // so exporting both rows doubles the revenue for those jobs and nothing in
        // the CSV lets the importer tell. The summary is the document the customer
        // actually received and paid against, so that is the row we keep.
        //
        // A child is dropped only when its summary is present in this same export.
        // If the range catches the children but not the summary that was cut later,
        // dropping them would silently lose the revenue instead of double-counting
        // it - the worse of the two failures - so those children are kept.
        $exportedIds = $invoices->pluck('id')->all();
        $exportedIds = array_flip($exportedIds);

        $invoices = $invoices->reject(function ($invoice) use ($exportedIds) {
            return $invoice->parent_invoice_id !== null
                && isset($exportedIds[$invoice->parent_invoice_id]);
        })->values();

        // With the children gone, their expense detail would have gone with
        // them, since a summary stores no expense scalars of its own (TASK-379).
        // The summary row therefore rolls its children's figures up at export
        // time. This is computed here and never written back to the summary's
        // `values` -- storing it is what TASK-379 fixed, and the summary
        // document itself must keep printing no expenses.
        $invoices->loadMissing(['children.job']);

        $filename = 'quickbooks-export-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            // Enhanced header with more QuickBooks-friendly fields
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

                // Build customer address
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

                // Extract values - handle both flat and nested structures
                $totals = is_array($values['total'] ?? null) ? $values['total'] : [];
                
                // Get billable miles from various possible locations
                $billableMiles = $values['billable_miles'] 
                    ?? $totals['billable_miles'] 
                    ?? ($job ? $job->miles?->billable : null)
                    ?? 0;

                // A summary carries no expenses of its own, so its row reports
                // what its children add up to. A single invoice reports itself.
                $children = $invoice->isSummary() ? $invoice->children : collect();
                $figures = $children->isNotEmpty()
                    ? $this->rollUpFigures($children)
                    : $this->invoiceFigures($invoice);
                $summaryDetail = $children->isNotEmpty()
                    ? $this->summaryDetail($children)
                    : '';

                $row = [
                    $invoice->invoice_number,
                    optional($invoice->created_at)->format('m/d/Y'), // QuickBooks date format
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
     *
     * A summary stores no expense scalars of its own -- TASK-379 removed them
     * because it had been inheriting one arbitrary child's. Since TASK-383 stops
     * exporting the children alongside it, rolling them up here is the only way
     * the detail reaches the sheet at all. It stays a read: nothing is written
     * back, so the summary document still prints no expenses.
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
     * Same reasoning as extraChargeDetail() below: the CSV is one row per
     * invoice, so N children cannot each become a column. They ride in a single
     * text column, ready to paste into a QuickBooks memo or line description.
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
     * The CSV is one row per invoice, so N charges cannot each become a column
     * without either dynamic headers or an IIF-style line-item restructure.
     * They ride in a single adjacent text column instead: the scalar
     * "Expenses (Extra Charges)" stays the authoritative figure that totals
     * against, and this one says what it was made of, ready to paste into a
     * QuickBooks memo or description.
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
