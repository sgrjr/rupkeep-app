<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The filter panel shared by both invoice exports.
 *
 * There are two exports because there are two questions. The job CSV is the
 * operational register -- one row per job, the shape the legacy spreadsheet
 * had and the shape our own importer reads. The QuickBooks CSV is an
 * accounts-receivable feed -- one invoice per document the customer was
 * actually billed, itemized into lines.
 *
 * They share a filter panel and a de-duplication *problem*, but they resolve
 * it in opposite directions, which is the whole reason they are separate. See
 * each controller.
 */
trait FiltersInvoiceExports
{
    /**
     * @return array{from: ?string, to: ?string, customer_id: ?int, paid: ?string}
     */
    protected function exportFilters(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'customer_id' => ['nullable', 'integer'],
            'paid' => ['nullable', 'in:yes,no'],
        ]);

        // Normalize empty strings to null so a blank field is not read as a filter.
        foreach (['from', 'to', 'customer_id', 'paid'] as $key) {
            $data[$key] = ! empty($data[$key] ?? null) ? $data[$key] : null;
        }

        return $data;
    }

    /**
     * Every invoice matching the filter panel, newest first.
     *
     * No limit and no pagination: an empty filter panel means "export
     * everything", not "export the first page".
     */
    protected function filteredInvoices(Request $request, array $data): Collection
    {
        $query = Invoice::query()
            ->with(['customer', 'job'])
            ->where('organization_id', $request->user()->organization_id)
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

        return $query->limit(null)->get();
    }

    /**
     * Only admins, managers and supers may pull financial data out of the app.
     */
    protected function authorizeExport(Request $request): void
    {
        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isManager() && ! $user->isSuper()) {
            abort(403);
        }
    }

    /**
     * The ids present in this export, as a lookup.
     */
    protected function idLookup(Collection $invoices): array
    {
        return array_flip($invoices->pluck('id')->all());
    }
}
