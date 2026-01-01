<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuickBooksExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isManager() && ! $user->is_super) {
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
                'Deadhead Count',
                'Deadhead Amount',
                'Mini Charge',
                'Total Amount',
                'Paid Status',
                'Payment Date',
                'Check Number',
                'Memo',
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
                $expenses = is_array($values['expenses'] ?? null) ? $values['expenses'] : [];
                
                // Get billable miles from various possible locations
                $billableMiles = $values['billable_miles'] 
                    ?? $totals['billable_miles'] 
                    ?? ($job ? $job->miles?->billable : null)
                    ?? 0;

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
                    number_format((float) ($totals['subtotal'] ?? $totals['base'] ?? ($values['subtotal'] ?? 0)), 2, '.', ''),
                    number_format((float) ($expenses['hotel'] ?? $values['hotel'] ?? 0), 2, '.', ''),
                    number_format((float) ($expenses['tolls'] ?? $values['tolls'] ?? 0), 2, '.', ''),
                    number_format((float) ($expenses['gas'] ?? $values['gas'] ?? 0), 2, '.', ''),
                    number_format((float) ($expenses['wait_time'] ?? $values['wait_time_hours'] ?? 0), 2, '.', ''),
                    number_format((float) ($expenses['extra_charge'] ?? $values['extra_charge'] ?? 0), 2, '.', ''),
                    $values['deadhead_count'] ?? $totals['deadhead_count'] ?? ($job && $job->is_deadhead ? 1 : 0),
                    number_format((float) ($totals['deadhead'] ?? $values['dead_head_charge'] ?? 0), 2, '.', ''),
                    number_format((float) ($totals['mini'] ?? $values['mini_cost'] ?? 0), 2, '.', ''),
                    number_format((float) ($totals['total'] ?? ($values['total'] ?? 0)), 2, '.', ''),
                    $invoice->paid_in_full ? 'Paid' : 'Unpaid',
                    $invoice->paid_in_full && $invoice->updated_at ? $invoice->updated_at->format('m/d/Y') : '',
                    optional($job)->check_no ?? '',
                    $values['notes'] ?? $values['memo'] ?? ($job ? $job->memo : '') ?? '',
                ];

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, $headers);
    }
}

