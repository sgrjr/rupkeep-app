<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The invoice list, shared by the organization-scoped screen at /my/invoices
 * and the cross-organization one at /invoices.
 *
 * The scoping is deliberately NOT in here. Each controller applies its own and
 * is responsible for it, so that the org-scoped screen cannot accidentally
 * inherit a missing filter from the super-user one. That is the failure mode
 * /jobs already has: JobsController::index applies no organization filter
 * unless a request parameter happens to supply one.
 */
trait BuildsInvoiceIndex
{
    protected function invoiceIndexFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'paid' => ['nullable', 'in:yes,no'],
            'type' => ['nullable', 'in:single,summary'],
            'orphaned' => ['nullable', 'in:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'organization_id' => ['nullable', 'integer'],
        ]);
    }

    /**
     * Everything except the tenancy scope. Never joins to pilot_car_jobs: an
     * invoice whose job row is gone is still a bill somebody owes, and is
     * precisely what these screens exist to reach.
     */
    protected function invoiceIndexQuery(array $filters): Builder
    {
        $query = Invoice::query()->with(['customer', 'job']);

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(function ($q) use ($term) {
                $like = '%'.$term.'%';

                $q->where('invoice_number', 'like', $like)
                    // values is a JSON blob; job_no and load_no live in there for
                    // invoices whose job row is gone.
                    ->orWhere('values', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        if (($filters['paid'] ?? null) !== null) {
            $query->where('paid_in_full', $filters['paid'] === 'yes');
        }

        if (($filters['type'] ?? null) === 'summary') {
            $query->where('invoice_type', 'summary');
        } elseif (($filters['type'] ?? null) === 'single') {
            $query->where(fn ($q) => $q->whereNull('invoice_type')->orWhere('invoice_type', '!=', 'summary'));
        }

        if (($filters['orphaned'] ?? null) === '1') {
            $query->where(function ($q) {
                $q->whereNull('pilot_car_job_id')->orWhereDoesntHave('job');
            });
        }

        if ($from = ($filters['from'] ?? null)) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = ($filters['to'] ?? null)) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * The view payload. The total is summed over the whole filter rather than
     * the current page, so it can be reconciled against the dashboard tile.
     */
    protected function invoiceIndexPayload(Builder $query, array $filters, bool $crossOrganization = false): array
    {
        $summed = (clone $query)->get(['id', 'values']);

        return [
            'invoices' => $query->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'listedTotal' => $summed->sum(fn (Invoice $invoice) => (float) data_get($invoice->values, 'total', 0)),
            'listedCount' => $summed->count(),
            'filters' => $filters,
            'crossOrganization' => $crossOrganization,
        ];
    }
}
