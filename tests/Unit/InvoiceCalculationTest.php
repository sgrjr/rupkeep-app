<?php

namespace Tests\Unit;

use App\Models\PilotCarJob;
use App\Models\UserLog;
use Tests\TestCase;

/**
 * Unit coverage for the invoice money math (TASK-305): rate-code branches in
 * calculateTotalDue(), the mini flat-rate threshold, add-on stacking, expense
 * charges, and the per-log expense/mileage aggregators. Everything runs on
 * in-memory models with organization_id = null so pricing comes from
 * config/pricing.php defaults (no DB, no PricingSetting lookups).
 */
class InvoiceCalculationTest extends TestCase
{
    private function job(): PilotCarJob
    {
        return new PilotCarJob();
    }

    /**
     * A minimal calculateTotalDue() input; override per test.
     */
    private function totals(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => null,
            'tolls' => '0.00',
            'hotel' => '0.00',
            'extra_charge' => '0.00',
            'extra_load_stops_count' => 0,
            'wait_time_hours' => 0,
            'dead_head' => 0,
            'dead_head_driven' => 0,
            'dead_head_billed' => 0,
            'rate_code' => 'lead_chase_per_mile',
            'rate_value' => null,
            'billable_miles' => 0,
            'mini_addon_amount' => 0,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // Rate-code branches
    // ---------------------------------------------------------------

    public function test_lead_chase_per_mile_uses_config_rate(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'billable_miles' => 100,
        ]));

        $this->assertSame(200.0, $result['total']); // 100 mi × $2.00
        $this->assertSame('lead_chase_per_mile', $result['effective_rate_code']);
        $this->assertSame(2.00, $result['effective_rate_value']);
    }

    public function test_legacy_per_mile_rate_uses_job_rate_value(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'per_mile_rate_2_50',
            'rate_value' => 2.50,
            'billable_miles' => 100,
        ]));

        $this->assertSame(250.0, $result['total']);
        $this->assertSame('per_mile_rate', $result['effective_rate_code']);
    }

    public function test_legacy_per_mile_rate_defaults_to_two_dollars_when_rate_value_missing(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'per_mile_rate',
            'rate_value' => null,
            'billable_miles' => 50,
        ]));

        $this->assertSame(100.0, $result['total']);
    }

    public function test_unknown_rate_code_falls_back_to_default_per_mile(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'definitely_not_a_rate',
            'billable_miles' => 10,
        ]));

        $this->assertSame(20.0, $result['total']); // fallback $2.00/mi
        $this->assertSame('per_mile_rate', $result['effective_rate_code']);
    }

    public function test_mini_flat_rate_applies_at_or_under_max_miles(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'mini_flat_rate',
            'billable_miles' => 125, // exactly the config max
        ]));

        $this->assertSame(350.0, $result['total']);
        $this->assertSame('mini_flat_rate', $result['effective_rate_code']);
    }

    public function test_mini_flat_rate_falls_back_to_per_mile_over_max_miles(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'mini_flat_rate',
            'billable_miles' => 200,
        ]));

        $this->assertSame(400.0, $result['total']); // 200 mi × $2.00 beats the mini
        $this->assertSame('lead_chase_per_mile', $result['effective_rate_code']);
    }

    public function test_named_flat_rates_use_config_amounts(): void
    {
        foreach ([
            'show_no_go' => 225.0,
            'cancellation_24hr' => 150.0,
            'cancel_without_billing' => 0.0,
            'day_rate' => 575.0,
        ] as $code => $expected) {
            $result = $this->job()->calculateTotalDue($this->totals([
                'rate_code' => $code,
            ]));

            $this->assertSame($expected, $result['total'], "flat rate {$code}");
        }
    }

    public function test_legacy_flat_rate_adds_expenses(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'flat_rate',
            'rate_value' => 500,
            'tolls' => '20.00',
            'hotel' => '80.00',
        ]));

        $this->assertSame(600.0, $result['total']);
        $this->assertSame('flat_rate', $result['effective_rate_code']);
    }

    public function test_flat_rate_excludes_expenses_ignores_expenses(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'flat_rate_excludes_expenses',
            'rate_value' => 500,
            'tolls' => '100.00',
            'hotel' => '50.00',
            'extra_charge' => '25.00',
        ]));

        $this->assertSame(500.0, $result['total']);
    }

    // ---------------------------------------------------------------
    // Expense charges
    // ---------------------------------------------------------------

    public function test_every_logged_wait_hour_bills(): void
    {
        // No free first hour (TASK-365): an hour that should not be billed is
        // handled by not logging it, not by the invoice discounting it.
        $result = $this->job()->calculateTotalDue($this->totals([
            'wait_time_hours' => 1,
        ]));

        $this->assertSame(30.0, $result['wait_time']);
    }

    public function test_wait_time_charged_per_hour_at_the_config_rate(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'wait_time_hours' => 3,
        ]));

        // 3 × config rate ($30.00/hr)
        $this->assertSame(90.0, $result['wait_time']);
        $this->assertSame(90.0, $result['total']);
    }

    public function test_a_configured_grace_period_is_honored_when_an_org_sets_one(): void
    {
        // minimum_hours defaults to 0 but stays editable at /my/pricing. It was
        // previously ignored entirely, with one free hour hard-coded instead.
        config()->set('pricing.charges.wait_time.minimum_hours', 2);

        $result = $this->job()->calculateTotalDue($this->totals([
            'wait_time_hours' => 3,
        ]));

        $this->assertSame(30.0, $result['wait_time']); // (3 − 2) × $30.00
    }

    public function test_a_grace_period_never_produces_a_negative_charge(): void
    {
        config()->set('pricing.charges.wait_time.minimum_hours', 2);

        $result = $this->job()->calculateTotalDue($this->totals([
            'wait_time_hours' => 1,
        ]));

        $this->assertSame(0.0, $result['wait_time']);
    }

    public function test_extra_stops_charged_per_stop(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'extra_load_stops_count' => 2,
        ]));

        $this->assertSame(60.0, $result['load_stops']); // 2 × $30.00
    }

    public function test_expenses_ride_on_top_of_per_mile_charge(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'billable_miles' => 100,
            'tolls' => '15.50',
            'hotel' => '120.00',
            'extra_charge' => '10.25',
            'extra_load_stops_count' => 1,
            'wait_time_hours' => 2,
        ]));

        // 200 miles charge + 15.50 + 120 + 10.25 + 30 stops + 60 wait (2 hrs)
        $this->assertSame(435.75, $result['total']);
    }

    // ---------------------------------------------------------------
    // Deadhead (TASK-354)
    // ---------------------------------------------------------------

    /**
     * Historical invoices were snapshotted before deadhead had any miles on
     * it: their values blob carries `dead_head` as a COUNT of flagged logs and
     * nothing else. Re-costing one of those must still produce no deadhead
     * charge, or every old invoice would silently gain money the customer was
     * never quoted. This is the regression guard for the pre-TASK-354 world,
     * which is why it asserts on a count and sets no mileage at all.
     */
    public function test_legacy_deadhead_count_alone_adds_no_charge(): void
    {
        $totals = $this->totals(['dead_head' => 3]);
        unset($totals['dead_head_driven'], $totals['dead_head_billed']);

        $with = $this->job()->calculateTotalDue($totals);
        $without = $this->job()->calculateTotalDue($this->totals(['dead_head' => 0]));

        $this->assertSame($without['total'], $with['total']);
        $this->assertSame(0.0, $with['dead_head_charge']);
    }

    /**
     * Driving deadhead is not the same as charging for it. The ledger figure
     * prices nothing on its own; a human has to decide to bill it.
     */
    public function test_deadhead_driven_alone_adds_no_charge(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'dead_head_driven' => 279,
            'dead_head_billed' => 0,
        ]));

        $this->assertSame(0.0, $result['dead_head_charge']);
        $this->assertSame(0.0, $result['total']);
    }

    public function test_billed_deadhead_miles_charge_at_the_config_rate(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'billable_miles' => 100,
            'dead_head_driven' => 279,
            'dead_head_billed' => 204,
        ]));

        // 100 mi at $2.00 escort, plus 204 mi at $1.00 deadhead
        $this->assertSame(404.0, $result['total']);
        $this->assertSame(204.0, $result['dead_head_charge']);
        $this->assertSame(1.00, $result['dead_head_rate']);
    }

    /**
     * The charge follows what was BILLED, not what was driven, so an ad-hoc
     * decision to forgive more than the published allowance carries through to
     * the total untouched.
     */
    public function test_partial_billing_charges_only_what_was_billed(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'dead_head_driven' => 279,
            'dead_head_billed' => 200,
        ]));

        $this->assertSame(200.0, $result['dead_head_charge']);
    }

    /**
     * Deadhead is an expense bucket, so a flat-rate job that excludes expenses
     * excludes it too, on the same branch that already drops tolls and hotel.
     */
    public function test_deadhead_is_excluded_from_flat_rate_excluding_expenses(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'flat_rate_excludes_expenses',
            'rate_value' => 500,
            'dead_head_driven' => 279,
            'dead_head_billed' => 204,
        ]));

        $this->assertSame(500.0, $result['total']);
    }

    /**
     * A show-but-no-go still moved a vehicle to the pickup. Job miles are zero
     * and the flat fee stands, but the approach was real and is billable if
     * someone says so. That is the whole point of keeping the tracking lever
     * and the billing lever separate.
     */
    public function test_deadhead_bills_on_a_show_no_go(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'show_no_go',
            'billable_miles' => 0,
            'dead_head_driven' => 150,
            'dead_head_billed' => 75,
        ]));

        $this->assertSame(300.0, $result['total']); // $225 flat plus 75 mi
    }

    // ---------------------------------------------------------------
    // Mini add-on stacking (TASK-307)
    // ---------------------------------------------------------------

    public function test_mini_addon_stacks_on_per_mile_total(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'billable_miles' => 100,
            'mini_addon_amount' => 75,
        ]));

        $this->assertSame(275.0, $result['total']);
        $this->assertSame(75.0, $result['mini_addon_amount']);
    }

    public function test_mini_addon_stacks_even_on_flat_rate_excluding_expenses(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'flat_rate_excludes_expenses',
            'rate_value' => 500,
            'tolls' => '100.00',
            'mini_addon_amount' => 75,
        ]));

        $this->assertSame(575.0, $result['total']); // expenses out, add-on in
    }

    // ---------------------------------------------------------------
    // Money parsing regressions (TASK-353)
    // ---------------------------------------------------------------

    public function test_comma_formatted_expenses_are_not_truncated(): void
    {
        // (float)"1,234.00" is 1.0 — this was billing $1 for $1,234 of tolls.
        $result = $this->job()->calculateTotalDue($this->totals([
            'tolls' => '1,234.00',
        ]));

        $this->assertSame(1234.0, $result['tolls']);
        $this->assertSame(1234.0, $result['total']);
    }

    public function test_comma_formatted_rate_value_is_not_truncated(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'rate_code' => 'flat_rate',
            'rate_value' => '1,500',
        ]));

        $this->assertSame(1500.0, $result['total']);
    }

    // ---------------------------------------------------------------
    // Per-log expense aggregators (TASK-353)
    // ---------------------------------------------------------------

    public function test_expense_aggregators_keep_cents_and_emit_no_thousands_separator(): void
    {
        $logs = collect([
            new UserLog(['tolls' => 12.75, 'hotel' => 110.50, 'extra_charge' => 1000.25]),
            new UserLog(['tolls' => 10.50, 'hotel' => 89.75, 'extra_charge' => 500.50]),
        ]);

        $job = $this->job();

        // Cents used to be dropped by an (Int) cast; totals >= 1000 used to
        // gain a comma that the downstream (float) cast truncated to ~$1.
        $this->assertSame('23.25', $job->getTotalTolls($logs));
        $this->assertSame('200.25', $job->getTotalHotel($logs));
        $this->assertSame('1500.75', $job->getExtraCharges($logs));
    }

    /**
     * The trip count now means "escorts actually charged deadhead", not "logs
     * with a box ticked". A log that drove an approach and had it forgiven is
     * not a billed leg and must not appear on the invoice as one.
     */
    public function test_deadhead_trip_count_counts_billed_logs_only(): void
    {
        $logs = collect([
            new UserLog(['dead_head_driven' => 120, 'dead_head_billed' => 45]),
            new UserLog(['dead_head_driven' => 90, 'dead_head_billed' => 0]),
            new UserLog(['dead_head_driven' => 200, 'dead_head_billed' => 125]),
        ]);

        $this->assertSame(2, $this->job()->getTotalDeadHead($logs));
    }

    /**
     * Doctrine: every deadhead mile is tracked whether or not it is billed, so
     * the driven total counts the forgiven log too.
     */
    public function test_driven_deadhead_totals_every_log_billed_or_not(): void
    {
        $logs = collect([
            new UserLog(['dead_head_driven' => 120, 'dead_head_billed' => 45]),
            new UserLog(['dead_head_driven' => 90, 'dead_head_billed' => 0]),
            new UserLog(['dead_head_driven' => 200, 'dead_head_billed' => 125]),
        ]);

        $this->assertSame(410.0, $this->job()->getTotalDeadHeadDriven($logs));
        $this->assertSame(170.0, $this->job()->getTotalDeadHeadBilled($logs));
    }

    /**
     * The approach leg is what the odometer already describes: clock-on to job
     * start. Release miles are the tail after the job let the driver go, and
     * are tracked but never billed.
     */
    public function test_odometer_readings_split_into_approach_and_release(): void
    {
        $log = new UserLog([
            'start_mileage' => 1000,
            'start_job_mileage' => 1069,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
        ]);

        $this->assertTrue($log->hasOrderedMileageReadings());
        $this->assertSame(69.0, $log->approach_miles);
        $this->assertSame(139.0, $log->release_miles);
    }

    /**
     * Production holds logs whose readings cannot describe a trip at all (one
     * implies a 190,065-mile approach). Those must report "unknown" rather
     * than a confident wrong number that would seed a suggested charge.
     */
    public function test_out_of_order_readings_yield_no_approach(): void
    {
        $log = new UserLog([
            'start_mileage' => 1000,
            'start_job_mileage' => 900,
            'end_job_mileage' => 1198,
            'end_mileage' => 1337,
        ]);

        $this->assertFalse($log->hasOrderedMileageReadings());
        $this->assertNull($log->approach_miles);
        $this->assertNull($log->release_miles);
    }

    /**
     * The published free allowance is a ceiling on what may be billed. It is
     * per log, so a two-car job gives each escort its own allowance and the
     * office can still bill one car and forgive the other by what it enters.
     */
    public function test_billing_ceiling_is_driven_less_the_free_allowance(): void
    {
        $log = new UserLog(['dead_head_driven' => 279]);

        $this->assertSame(75.0, $log->deadHeadFreeMiles());
        $this->assertSame(204.0, $log->deadHeadBillingCeiling());
    }

    public function test_billing_ceiling_is_zero_inside_the_free_allowance(): void
    {
        $log = new UserLog(['dead_head_driven' => 69]);

        $this->assertSame(0.0, $log->deadHeadBillingCeiling());
    }

    // ---------------------------------------------------------------
    // The read-only summary sentence
    // ---------------------------------------------------------------

    public function test_summary_says_nothing_was_recorded(): void
    {
        $this->assertSame(
            'No deadhead miles recorded.',
            (new UserLog())->deadHeadSummary()
        );
    }

    /**
     * The distinction this sentence exists for: a short approach had nothing
     * billable in it, while a long one that billed nothing was somebody
     * choosing to forgive it. The raw numbers look the same; the reasons do
     * not, and only one of them is a decision worth reviewing.
     */
    public function test_summary_separates_no_charge_from_forgiven(): void
    {
        $this->assertSame(
            '17 deadhead miles driven, none billable (within the first 75 free).',
            (new UserLog(['dead_head_driven' => 17]))->deadHeadSummary()
        );

        $this->assertSame(
            '100 deadhead miles driven, none billed (up to 25 could have been).',
            (new UserLog(['dead_head_driven' => 100]))->deadHeadSummary()
        );
    }

    public function test_summary_reports_a_full_charge_and_the_free_allowance(): void
    {
        $this->assertSame(
            '100 deadhead miles driven, 25 billed (first 75 free).',
            (new UserLog(['dead_head_driven' => 100, 'dead_head_billed' => 25]))->deadHeadSummary()
        );
    }

    /**
     * Extra grace beyond the published allowance is called out separately, so
     * a concession the office chose to make does not read as policy.
     */
    public function test_summary_calls_out_grace_beyond_the_allowance(): void
    {
        $this->assertSame(
            '279 deadhead miles driven, 200 billed (first 75 free, 4 more not charged).',
            (new UserLog(['dead_head_driven' => 279, 'dead_head_billed' => 200]))->deadHeadSummary()
        );
    }

    /**
     * An approach landing exactly on the allowance has a ceiling of zero, so
     * it reads as "nothing billable", never as a forgiven decision.
     */
    public function test_summary_treats_an_exact_allowance_as_not_billable(): void
    {
        $this->assertSame(
            '75 deadhead miles driven, none billable (within the first 75 free).',
            (new UserLog(['dead_head_driven' => 75]))->deadHeadSummary()
        );
    }

    // ---------------------------------------------------------------
    // Mileage aggregation
    // ---------------------------------------------------------------

    public function test_billable_miles_come_from_job_mileage(): void
    {
        $logs = collect([
            new UserLog([
                'start_mileage' => 1000,
                'end_mileage' => 1300,
                'start_job_mileage' => 1050,
                'end_job_mileage' => 1250,
            ]),
        ]);

        $miles = $this->job()->getTotalMiles($logs);

        $this->assertSame(200, $miles['total_billable']); // 1250 − 1050
        $this->assertSame(100, $miles['total_nonbillable']); // 300 total − 200 job
    }

    public function test_manual_billable_override_wins(): void
    {
        $logs = collect([
            new UserLog([
                'start_mileage' => 1000,
                'end_mileage' => 1300,
                'start_job_mileage' => 1050,
                'end_job_mileage' => 1250,
                'billable_miles' => 180.5,
            ]),
        ]);

        $miles = $this->job()->getTotalMiles($logs);

        $this->assertSame(180.5, $miles['total_billable']);
    }

    public function test_negative_billable_miles_clamped_to_zero(): void
    {
        // end_job < start_job (bad data entry) must not produce a credit.
        $logs = collect([
            new UserLog([
                'start_mileage' => 1000,
                'end_mileage' => 1100,
                'start_job_mileage' => 1090,
                'end_job_mileage' => 1010,
            ]),
        ]);

        $miles = $this->job()->getTotalMiles($logs);

        $this->assertSame(0, $miles['total_billable']);
    }

    public function test_billable_miles_sum_across_logs(): void
    {
        $logs = collect([
            new UserLog(['start_mileage' => 0, 'end_mileage' => 120, 'start_job_mileage' => 10, 'end_job_mileage' => 110]),
            new UserLog(['start_mileage' => 0, 'end_mileage' => 80, 'start_job_mileage' => 5, 'end_job_mileage' => 55]),
        ]);

        $miles = $this->job()->getTotalMiles($logs);

        $this->assertSame(150, $miles['total_billable']); // 100 + 50
    }
}
