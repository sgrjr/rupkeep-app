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

        $query = Invoice::query()
            ->with(['customer', 'job'])
            ->where('organization_id', $user->organization_id)
            ->orderByDesc('created_at');

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

        $invoices = $query->get();

        $filename = 'quickbooks-export-' . now()->format('Ymd-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            $header = [
                'Invoice Number',
                'Invoice Date',
                'Customer',
                'Job Number',
                'Billable Miles',
                'Amount',
                'Paid',
                'Memo',
            ];

            fputcsv($handle, $header);

            foreach ($invoices as $invoice) {
                $values = is_array($invoice->values) ? $invoice->values : [];
                $job = $invoice->job;

                $row = [
                    $invoice->invoice_number,
                    optional($invoice->created_at)->toDateString(),
                    optional($invoice->customer)->name,
                    optional($job)->job_no,
                    $values['billable_miles'] ?? '',
                    number_format((float) ($values['total'] ?? 0), 2, '.', ''),
                    $invoice->paid_in_full ? 'Yes' : 'No',
                    $values['notes'] ?? '',
                ];

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, $headers);
    }
}

