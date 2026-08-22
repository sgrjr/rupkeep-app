<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Builds and refreshes the `values` blob of a summary invoice.
 *
 * A summary is a cover sheet: one row per child invoice at that child's total,
 * and nothing else. It therefore carries NO per-job scalars of its own -- no
 * hotel, tolls, extra_charge, rate_code, job_no, wait time. It used to seed
 * itself from the first child's whole values array, so a summary silently
 * inherited one arbitrary child's expense figures as dead keys, which the
 * QuickBooks export then wrote out as if they were the summary's own
 * (TASK-379).
 *
 * Carrying none rather than rolling them up is deliberate. Every child's
 * expenses are already inside the child total this sums, and are itemized on
 * the child invoice, which stays individually openable and is linked from the
 * summary. A rolled-up figure would be a second set of numbers no template
 * prints.
 *
 * Only presentation carries over from the first child -- who the invoice is
 * from and to, and the logo/footer chrome. All children share a customer and
 * an organization (every call site enforces it), so that much is not arbitrary.
 *
 * This lived in MyInvoicesController and ran only at creation, which made a
 * summary a snapshot taken the moment it was cut: edit a child and the cover
 * sheet kept printing and billing the old figure (TASK-381). It moved here so
 * InvoiceObserver can re-run it whenever a child changes.
 */
class SummaryInvoiceValues
{
    /**
     * Marks a summary whose stored total was set by hand rather than summed
     * from its children. Such a summary is never silently recomputed.
     */
    public const OVERRIDE_KEY = 'total_override';

    /**
     * Set on an overridden summary when its children no longer sum to the
     * stored total. Holds what the children DO sum to, so the edit screen can
     * name both figures.
     */
    public const STALE_KEY = 'summary_stale_total';

    /** The keys refresh() owns. Everything else on a summary is the admin's. */
    private const COMPUTED_KEYS = [
        'total',
        'billable_miles',
        'summary_items',
        'child_invoice_ids',
    ];

    /** Money compares to the cent; anything finer is float noise. */
    private const EPSILON = 0.005;

    /**
     * Build the values blob for a summary covering the given child invoices.
     */
    public static function build(Collection $childInvoices): array
    {
        $firstValues = $childInvoices->first()->values ?? [];

        $baseValues = Arr::only($firstValues, ['bill_from', 'bill_to', 'logo', 'footer']);

        $total = 0.0;
        $billableMiles = 0.0;
        $items = [];

        foreach ($childInvoices as $child) {
            $childValues = $child->values ?? [];
            $childTotal = (float) data_get($childValues, 'total', 0);
            $childMiles = (float) data_get($childValues, 'billable_miles', 0);

            $total += $childTotal;
            $billableMiles += $childMiles;

            $pickupAddress = data_get($childValues, 'pickup_address');
            $deliveryAddress = data_get($childValues, 'delivery_address');
            $description = Invoice::generateDescriptionOfWork($pickupAddress, $deliveryAddress);

            // The job may be gone (force-deleted) while the invoice remains, so
            // every read below goes through optional chaining rather than
            // assuming a relation.
            $job = $child->job;

            $items[] = [
                'invoice_id' => $child->id,
                'invoice_number' => $child->invoice_number ?? '—',
                'title' => data_get($childValues, 'title', 'INVOICE'),
                'job_no' => data_get($childValues, 'job_no') ?? $job?->job_no ?? data_get($childValues, 'load_no') ?? '—',
                'load_no' => data_get($childValues, 'load_no') ?? $job?->load_no ?? '—',
                'pickup_address' => $pickupAddress ?? $job?->pickup_address ?? '—',
                'delivery_address' => $deliveryAddress ?? $job?->delivery_address ?? '—',
                'description' => $description ?? '—',
                'total' => $childTotal,
                'billable_miles' => $childMiles,
                'rate_code' => data_get($childValues, 'effective_rate_code') ?? data_get($childValues, 'rate_code') ?? $job?->rate_code ?? '—',
                // When the WORK happened, not when the invoice was cut (TASK-345).
                // These were previously all the child invoice's created_at, so
                // every row of a monthly summary carried the same date.
                'date_of_service' => $job?->scheduled_pickup_at
                    ?? data_get($childValues, 'start_job_time')
                    ?? $child->created_at?->format('Y-m-d')
                    ?? '—',
                // The TASK-344 required fields that map onto a summary row. A
                // summary aggregates several jobs, so these ride per-row rather
                // than in the single-invoice job block.
                'truck_driver_name' => trim((string) (data_get($childValues, 'truck_driver_name') ?? '')) ?: null,
                'truck_number' => trim((string) (data_get($childValues, 'truck_number') ?? '')) ?: null,
                'trailer_number' => trim((string) (data_get($childValues, 'trailer_number') ?? '')) ?: null,
                'canceled_at' => $job?->canceled_at ? (string) $job->canceled_at : null,
                'canceled_reason' => trim((string) ($job?->canceled_reason ?? '')) ?: null,
            ];
        }

        $baseValues['title'] = 'SUMMARY INVOICE';
        $baseValues['total'] = round($total, 2);
        $baseValues['billable_miles'] = round($billableMiles, 2);
        $baseValues['summary_items'] = $items;
        $baseValues['child_invoice_ids'] = $childInvoices->pluck('id')->all();

        return $baseValues;
    }

    /**
     * What the children of this summary currently sum to.
     */
    public static function childTotal(Invoice $summary): float
    {
        $children = $summary->children()->get();

        return round(
            $children->sum(fn (Invoice $child) => (float) data_get($child->values ?? [], 'total', 0)),
            2
        );
    }

    /**
     * Bring a summary back in step with its children after one of them changed.
     *
     * A summary whose total was set by hand is left completely alone -- both its
     * total and its line rows stay frozen at the figures the admin signed off
     * on, so the printed document never contradicts itself. It is marked stale
     * instead, and the edit screen offers to regenerate it.
     *
     * Returns true if anything was written.
     */
    public static function refresh(Invoice $summary): bool
    {
        if (! $summary->isSummary()) {
            return false;
        }

        $children = $summary->children()->with('job')->get();

        // A summary that has lost every child has nothing to recompute from.
        // Leave the last known figures rather than zeroing a document that may
        // already be in a customer's hands.
        if ($children->isEmpty()) {
            return false;
        }

        if (empty(($summary->values ?? [])[self::OVERRIDE_KEY])) {
            return self::regenerate($summary, $children);
        }

        return self::markStaleIfDrifted($summary, $children);
    }

    /**
     * Recompute a summary from its children unconditionally, clearing both the
     * override and the stale marker. This is what the "Regenerate" action on
     * the edit screen calls.
     */
    public static function regenerate(Invoice $summary, ?Collection $children = null): bool
    {
        if (! $summary->isSummary()) {
            return false;
        }

        $children ??= $summary->children()->with('job')->get();

        if ($children->isEmpty()) {
            return false;
        }

        $existing = $summary->values ?? [];

        // Keep whatever presentation the admin edited on the summary itself --
        // bill_to, footer, notes and friends belong to this document, not to any
        // child. Only the summed figures and the line rows are replaced.
        $merged = array_merge($existing, Arr::only(self::build($children), self::COMPUTED_KEYS));

        unset($merged[self::OVERRIDE_KEY], $merged[self::STALE_KEY]);

        if ($merged === $existing) {
            return false;
        }

        $summary->values = $merged;
        $summary->saveQuietly();

        return true;
    }

    /**
     * Flag (or clear) the stale marker on a summary whose total is an override.
     */
    private static function markStaleIfDrifted(Invoice $summary, Collection $children): bool
    {
        $values = $summary->values ?? [];

        $childTotal = round(
            $children->sum(fn (Invoice $child) => (float) data_get($child->values ?? [], 'total', 0)),
            2
        );

        $drifted = abs($childTotal - (float) data_get($values, 'total', 0)) > self::EPSILON;

        if ($drifted) {
            $values[self::STALE_KEY] = $childTotal;
        } else {
            unset($values[self::STALE_KEY]);
        }

        if ($values === ($summary->values ?? [])) {
            return false;
        }

        $summary->values = $values;
        $summary->saveQuietly();

        return true;
    }

    /**
     * Decide, at save time, whether the total an admin just posted on a summary
     * is their own figure or simply the sum of the children.
     */
    public static function markOverrideFromPostedTotal(Invoice $summary): void
    {
        if (! $summary->isSummary()) {
            return;
        }

        $values = $summary->values ?? [];
        $postedTotal = (float) data_get($values, 'total', 0);

        if (abs($postedTotal - self::childTotal($summary)) > self::EPSILON) {
            $values[self::OVERRIDE_KEY] = true;
            unset($values[self::STALE_KEY]);
        } else {
            unset($values[self::OVERRIDE_KEY], $values[self::STALE_KEY]);
        }

        $summary->values = $values;
    }
}
