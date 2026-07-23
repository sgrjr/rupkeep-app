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

    public function test_first_wait_hour_is_free(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'wait_time_hours' => 1,
        ]));

        $this->assertSame(0.0, $result['wait_time']);
    }

    public function test_wait_time_charged_per_hour_after_the_first(): void
    {
        $result = $this->job()->calculateTotalDue($this->totals([
            'wait_time_hours' => 3,
        ]));

        // (3 − 1) × config rate ($30.00/hr)
        $this->assertSame(60.0, $result['wait_time']);
        $this->assertSame(60.0, $result['total']);
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

        // 200 miles charge + 15.50 + 120 + 10.25 + 30 stops + 30 wait
        $this->assertSame(405.75, $result['total']);
    }

    public function test_deadhead_count_flows_through_but_adds_no_charge(): void
    {
        // Documents current behavior: config/pricing.php defines a dead_head
        // charge ($1/mi after 75 free) but calculateTotalDue never applies it;
        // 'dead_head' in totals is a log count used for invoice display only.
        $with = $this->job()->calculateTotalDue($this->totals(['dead_head' => 3]));
        $without = $this->job()->calculateTotalDue($this->totals(['dead_head' => 0]));

        $this->assertSame($without['total'], $with['total']);
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

    public function test_deadhead_counts_flagged_logs_only(): void
    {
        $logs = collect([
            new UserLog(['is_deadhead' => true]),
            new UserLog(['is_deadhead' => false]),
            new UserLog(['is_deadhead' => true]),
        ]);

        $this->assertSame(2, $this->job()->getTotalDeadHead($logs));
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
