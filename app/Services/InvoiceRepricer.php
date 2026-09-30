<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PilotCarJob;

/**
 * Re-price a single invoice's snapshot after the edit form changed it
 * (TASK-448).
 *
 * The invoice `values` blob carries two kinds of figure: the inputs someone
 * can edit (miles, wait hours, stops, billed deadhead, tolls, hotel, the rate)
 * and the outputs derived from them (total, cost_for_mileage,
 * cost_of_wait_time, dead_head_charge ...). The edit form used to write the
 * inputs and leave the outputs alone. Because InvoiceLineItems defines the
 * "Pilot Car Service" line as total minus everything itemized, every such
 * edit moved the service line by the same amount in the other direction:
 * raising tolls from $20 to $120 printed a service line $100 lower, and a big
 * enough edit printed a negative one.
 *
 * So the outputs are recomputed here whenever an input changed, through the
 * same PilotCarJob::priceValues() that cut the invoice in the first place.
 *
 * A hand-set total is kept, but as a named thing. When the posted total
 * differs from what the invoice held before, the difference between the
 * posted total and the recomputed one is stored as `adjustment` and printed
 * as its own "Discount" / "Adjustment" line. The service line is then exactly
 * the rate charge, and the lines still sum to the total. Later edits to a
 * quantity carry the adjustment forward unchanged: a $100 discount stays a
 * $100 discount when the tolls are corrected.
 *
 * An edit that touches neither a pricing input nor the total (a billing
 * address, a note) leaves the money alone. That matters for the ~1,000
 * historical invoices whose totals were imported rather than computed.
 */
class InvoiceRepricer
{
    public const ADJUSTMENT_KEY = 'adjustment';

    private const EPSILON = 0.005;

    /**
     * The keys whose change means the money has to be recomputed. The rate
     * pair prices the miles; the rest are quantities the price sheet bills.
     * `extra_charge` and `mini_addon_amount` are inputs too, but the form does
     * not post them (LogExtraCharges and the job own them), so a change to
     * either arrives through a different path.
     */
    public const PRICING_INPUTS = [
        'rate_code',
        'rate_value',
        'billable_miles',
        'wait_time_hours',
        'extra_load_stops_count',
        'dead_head_billed',
        'tolls',
        'hotel',
    ];

    /**
     * @param array<string, mixed> $before  the snapshot as it was stored
     * @param array<string, mixed> $after   the snapshot with the posted edits applied
     * @return array{values: array<string, mixed>, repriced: bool, adjustment: float}
     */
    public static function apply(Invoice $invoice, array $before, array $after): array
    {
        $adjustmentBefore = round((float) ($before[self::ADJUSTMENT_KEY] ?? 0), 2);

        if ($invoice->isSummary()) {
            // A summary totals its children; SummaryInvoiceValues owns that.
            return ['values' => $after, 'repriced' => false, 'adjustment' => $adjustmentBefore];
        }

        $inputsChanged = collect(self::PRICING_INPUTS)
            ->contains(fn (string $key) => self::differs($before[$key] ?? null, $after[$key] ?? null));

        $totalBefore = self::money($before['total'] ?? 0);
        $totalPosted = self::money($after['total'] ?? 0);
        $totalChanged = abs($totalPosted - $totalBefore) > self::EPSILON;

        if (! $inputsChanged && ! $totalChanged) {
            return ['values' => $after, 'repriced' => false, 'adjustment' => $adjustmentBefore];
        }

        $inputs = self::restoreUnbilledExpenses($before, $after);
        $inputs['organization_id'] ??= $invoice->organization_id;

        // A snapshot that was imported or hand-built may lack a bucket the
        // calculator reads by name; an absent charge is a zero charge.
        foreach (['tolls', 'hotel', 'extra_charge', 'extra_load_stops_count', 'wait_time_hours', 'dead_head_billed', 'billable_miles', 'mini_addon_amount'] as $key) {
            $inputs[$key] ??= 0;
        }
        $inputs['rate_code'] ??= '';

        // The job is the calculator, not the source of the figures: every
        // number comes from the snapshot, so an invoice whose job is gone
        // re-prices exactly like one whose job is still here.
        $job = $invoice->job ?? new PilotCarJob();
        if (! $invoice->job) {
            $job->organization_id = $invoice->organization_id;
        }

        $priced = $job->priceValues($inputs);
        $computedTotal = round((float) $priced['total'], 2);

        // A total the user typed is theirs; the gap to the math is the
        // adjustment. A total they left alone carries the old adjustment
        // forward on top of the new math.
        $adjustment = $totalChanged
            ? round($totalPosted - $computedTotal, 2)
            : $adjustmentBefore;

        if (abs($adjustment) < self::EPSILON) {
            $adjustment = 0.0;
            unset($priced[self::ADJUSTMENT_KEY]);
        } else {
            $priced[self::ADJUSTMENT_KEY] = $adjustment;
        }

        $priced['total'] = round($computedTotal + $adjustment, 2);

        return ['values' => $priced, 'repriced' => true, 'adjustment' => $adjustment];
    }

    /**
     * On a snapshot whose rate bills no expenses (TASK-445), tolls, hotel and
     * the extra charges are stored as 0 and the logged figures sit under
     * expenses_not_billed. Feed those back in when the form did not touch the
     * bucket, so priceValues() can zero and record them again rather than
     * forgetting what the drivers logged.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array<string, mixed>
     */
    private static function restoreUnbilledExpenses(array $before, array $after): array
    {
        if (($before['bills_expenses'] ?? true) !== false) {
            return $after;
        }

        $notBilled = is_array($before['expenses_not_billed'] ?? null) ? $before['expenses_not_billed'] : [];

        foreach (['tolls' => 'tolls', 'hotel' => 'hotel', 'extra' => 'extra_charge'] as $bucket => $key) {
            if (! isset($notBilled[$bucket])) {
                continue;
            }

            if (! self::differs($before[$key] ?? null, $after[$key] ?? null)) {
                $after[$key] = $notBilled[$bucket];
            }
        }

        if (isset($notBilled['extra_charges']) && empty($after['extra_charges'])) {
            $after['extra_charges'] = $notBilled['extra_charges'];
        }

        return $after;
    }

    private static function differs(mixed $a, mixed $b): bool
    {
        $aNumeric = $a === null || $a === '' || is_numeric(str_replace(',', '', (string) $a));
        $bNumeric = $b === null || $b === '' || is_numeric(str_replace(',', '', (string) $b));

        if ($aNumeric && $bNumeric) {
            return abs(self::money($a) - self::money($b)) > self::EPSILON;
        }

        return trim((string) $a) !== trim((string) $b);
    }

    private static function money(mixed $value): float
    {
        return (float) str_replace(',', '', (string) ($value ?? 0));
    }
}
