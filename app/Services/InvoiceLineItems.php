<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * The billed lines of a single (non-summary) invoice: description, quantity,
 * rate, amount.
 *
 * This is the breakdown printed on the invoice the customer receives, and it
 * has one property everything downstream depends on: **the amounts sum to the
 * invoice total, always.** That is not a coincidence to be re-derived, it is
 * built in -- the "Pilot Car Service" line is defined as the total minus every
 * itemized charge, so whatever the rate did, the lines reconcile.
 *
 * It lived inline in resources/views/invoices/templates/render.blade.php, which
 * was fine while the invoice document was the only thing that needed it. The
 * QuickBooks export needs the same lines (a QuickBooks invoice is line items,
 * not one lump), and a second hand-rolled breakdown would be a second set of
 * numbers that could disagree with the printed invoice. So it moved here and
 * the template calls it.
 *
 * A summary invoice has no lines of its own -- it is a cover sheet over child
 * invoices, each of which has its own. Callers wanting a summary's detail
 * should walk $invoice->children and call this per child.
 */
class InvoiceLineItems
{
    /**
     * Build the billed lines for one invoice's values blob.
     *
     * Zero-amount lines are dropped: they were never a layout requirement, and
     * "Wait Time 1 x $0.00" reads as a mistake to the customer (TASK-367).
     *
     * @return array<int, array{key: string, description: string, quantity: float|int, rate: float, amount: float}>
     */
    public static function build(array $values): array
    {
        $money = self::moneyParser();

        $waitTimeAmount = isset($values['cost_of_wait_time']) ? $money($values['cost_of_wait_time']) : 0;
        $extraStopsAmount = isset($values['cost_of_extra_stop']) && isset($values['extra_load_stops_count'])
            ? (float) ($values['extra_load_stops_count'] * $money($values['cost_of_extra_stop']))
            : 0;
        $deadAmount = isset($values['dead_head_charge']) ? $money($values['dead_head_charge']) : 0;
        $tollsAmount = isset($values['tolls']) ? $money($values['tolls']) : 0;
        $totalMileageAmount = isset($values['cost_for_mileage']) ? (float) $values['cost_for_mileage'] : 0;
        $miniAddonAmount = isset($values['mini_addon_amount']) ? (float) $values['mini_addon_amount'] : 0;
        // Hotel and extra charges are expense buckets that calculateTotalDue()
        // adds into `total`, but they were missing from the subtraction below,
        // so both vanished into the Pilot Car Service line (TASK-367). A $575
        // day rate with a $125 hotel and a $10 toll billed a correct $710 total
        // while showing "Pilot Car Service $700" and no hotel anywhere.
        $hotelAmount = isset($values['hotel']) ? $money($values['hotel']) : 0;
        $extraChargeAmount = isset($values['extra_charge']) ? $money($values['extra_charge']) : 0;

        $otherChargesTotal = $waitTimeAmount + $extraStopsAmount + $deadAmount + $tollsAmount
            + $totalMileageAmount + $miniAddonAmount + $hotelAmount + $extraChargeAmount;
        $pilotCarServiceAmount = (float) ($values['total'] ?? 0) - $otherChargesTotal;

        $lineItems = [];

        // Whatever the rate itself charged, once every itemized expense is
        // accounted for. On a per-mile job this is 0 and the Total Mileage line
        // carries the charge instead; on a flat/day rate it IS the flat amount.
        $lineItems[] = [
            'key' => 'pilot_car_service',
            'description' => __('Pilot Car Service'),
            'quantity' => 1,
            'rate' => $pilotCarServiceAmount,
            'amount' => $pilotCarServiceAmount,
        ];

        // Wait Time. Quantity is the BILLABLE hours,
        // not the logged hours: with a free-hour minimum configured, dividing
        // the amount by logged hours invented a rate that matched no published
        // price (3 hrs / $60 read as "3 x $20.00" instead of "2 x $30.00").
        // The free hours are called out in the description so the customer can
        // still reconcile the line against the hours the driver logged.
        $waitTimeLoggedHours = isset($values['wait_time_hours']) ? (float) $values['wait_time_hours'] : 0;
        $waitTimeQty = isset($values['wait_time_billable_hours'])
            ? (float) $values['wait_time_billable_hours']
            : $waitTimeLoggedHours;
        $waitTimeRate = isset($values['wait_time_rate']) && (float) $values['wait_time_rate'] > 0
            ? (float) $values['wait_time_rate']
            : ($waitTimeQty > 0 ? ($waitTimeAmount / $waitTimeQty) : 0);
        $waitTimeFreeHours = max(0, $waitTimeLoggedHours - $waitTimeQty);
        $lineItems[] = [
            'key' => 'wait_time',
            'description' => $waitTimeFreeHours > 0
                ? __('Wait Time (:logged hrs logged, first :free free)', [
                    'logged' => rtrim(rtrim(number_format($waitTimeLoggedHours, 2), '0'), '.'),
                    'free' => rtrim(rtrim(number_format($waitTimeFreeHours, 2), '0'), '.'),
                ])
                : __('Wait Time'),
            'quantity' => $waitTimeQty,
            'rate' => $waitTimeRate,
            'amount' => $waitTimeAmount,
        ];

        // Extra Stops
        $extraStopsQty = isset($values['extra_load_stops_count']) ? (float) $values['extra_load_stops_count'] : 0;
        $extraStopsRate = isset($values['cost_of_extra_stop'])
            ? $money($values['cost_of_extra_stop'])
            : ($extraStopsQty > 0 ? ($extraStopsAmount / $extraStopsQty) : 0);
        $lineItems[] = [
            'key' => 'extra_stops',
            'description' => __('Extra Stops'),
            'quantity' => $extraStopsQty,
            'rate' => $extraStopsRate,
            'amount' => $extraStopsAmount,
        ];

        // Dead Head. Quantity is the miles a human chose to BILL (TASK-354),
        // never the miles driven: the price sheet gives away the first
        // `free_miles` of every approach, and more than that can be forgiven
        // on any individual log. The description carries the driven figure so
        // the customer sees the whole approach and exactly how much of it went
        // uncharged - the concession is stated on the invoice, not silent.
        //
        // Historical invoices predate both quantities and stored only a count
        // of deadhead-flagged logs under `dead_head`, so an old snapshot falls
        // back to it and renders the way it always did.
        $deadDrivenMiles = isset($values['dead_head_driven']) ? (float) $values['dead_head_driven'] : 0;
        $deadQty = isset($values['dead_head_billed'])
            ? (float) $values['dead_head_billed']
            : (isset($values['dead_head']) ? (float) $values['dead_head'] : 0);
        $deadRate = isset($values['dead_head_rate']) && (float) $values['dead_head_rate'] > 0
            ? (float) $values['dead_head_rate']
            : ($deadQty > 0 ? ($deadAmount / $deadQty) : 0);
        $deadNotBilled = max(0, $deadDrivenMiles - $deadQty);
        $lineItems[] = [
            'key' => 'dead_head',
            'description' => $deadNotBilled > 0
                ? __('Dead Head Miles (:driven mi driven, :free not billed)', [
                    'driven' => rtrim(rtrim(number_format($deadDrivenMiles, 2), '0'), '.'),
                    'free' => rtrim(rtrim(number_format($deadNotBilled, 2), '0'), '.'),
                ])
                : __('Dead Head Miles'),
            'quantity' => $deadQty,
            'rate' => $deadRate,
            'amount' => $deadAmount,
        ];

        // Tolls
        $lineItems[] = [
            'key' => 'tolls',
            'description' => __('Tolls'),
            'quantity' => $tollsAmount > 0 ? 1 : 0,
            'rate' => $tollsAmount,
            'amount' => $tollsAmount,
        ];

        // Overnight / Hotel — a reimbursement, billed at actual cost.
        $lineItems[] = [
            'key' => 'hotel',
            'description' => __('Overnight / Hotel'),
            'quantity' => $hotelAmount > 0 ? 1 : 0,
            'rate' => $hotelAmount,
            'amount' => $hotelAmount,
        ];

        // Extra charges recorded against the job's logs. Each one carries its
        // own description now (TASK-330), so a one-off expense bills back as
        // "Equipment rental" rather than an unexplained "Extra Charges" lump.
        //
        // Snapshots written before TASK-330 have only the scalar and no array,
        // so they keep printing the single aggregate row exactly as before.
        // $extraChargeAmount stays the scalar either way -- it is what the
        // Pilot Car Service subtraction above is built from.
        $extraChargeLines = $values['extra_charges'] ?? null;

        if (is_array($extraChargeLines) && count($extraChargeLines) > 0) {
            foreach ($extraChargeLines as $extraChargeLine) {
                $lineAmount = (float) ($extraChargeLine['amount'] ?? 0);

                $lineItems[] = [
                    'key' => 'extra_charge',
                    'description' => $extraChargeLine['description'] ?: __('Extra Charges'),
                    'quantity' => $lineAmount != 0 ? 1 : 0,
                    'rate' => $lineAmount,
                    'amount' => $lineAmount,
                ];
            }
        } else {
            $lineItems[] = [
                'key' => 'extra_charge',
                'description' => __('Extra Charges'),
                'quantity' => $extraChargeAmount > 0 ? 1 : 0,
                'rate' => $extraChargeAmount,
                'amount' => $extraChargeAmount,
            ];
        }

        // Total Mileage
        $totalMileageQty = isset($values['billable_miles']) ? (float) $values['billable_miles'] : 0;
        $totalMileageRate = $totalMileageQty > 0 ? ($totalMileageAmount / $totalMileageQty) : 0;
        $lineItems[] = [
            'key' => 'mileage',
            'description' => __('Total Mileage'),
            'quantity' => $totalMileageQty,
            'rate' => $totalMileageRate,
            'amount' => $totalMileageAmount,
        ];

        // Mini Add-On (TASK-307): additive line item that stacks on top of
        // the rate above (including flat-rate jobs).
        $lineItems[] = [
            'key' => 'mini_addon',
            'description' => __('Mini Add-On'),
            'quantity' => $miniAddonAmount > 0 ? 1 : 0,
            'rate' => $miniAddonAmount,
            'amount' => $miniAddonAmount,
        ];

        // Only bill what was actually charged (TASK-367). The zero rows were
        // not a PDF layout requirement — these are plain table rows — they just
        // padded the invoice with "Wait Time 1 x $0.00" noise that read as a
        // mistake to the customer.
        return array_values(array_filter(
            $lineItems,
            fn ($item) => round((float) $item['amount'], 2) != 0.0
        ));
    }

    /**
     * The lines of an invoice, or of every child if it is a summary.
     *
     * A summary's own values carry no per-job charges (TASK-379), so asking a
     * summary for its lines has to mean asking its children. Each returned line
     * remembers which invoice it came from so an export can label it.
     *
     * @return array<int, array{invoice: Invoice, key: string, description: string, quantity: float|int, rate: float, amount: float}>
     */
    public static function forInvoice(Invoice $invoice): array
    {
        $sources = $invoice->isSummary()
            ? $invoice->children->all()
            : [$invoice];

        $lines = [];

        foreach ($sources as $source) {
            $values = is_array($source->values) ? $source->values : [];

            foreach (self::build($values) as $line) {
                $lines[] = ['invoice' => $source] + $line;
            }
        }

        return $lines;
    }

    /**
     * Comma-tolerant money parse: invoices created before TASK-353 stored
     * number_format()ed strings, and (float)"1,234.00" is 1.0.
     */
    private static function moneyParser(): callable
    {
        return fn ($v) => (float) str_replace(',', '', (string) ($v ?? 0));
    }
}
