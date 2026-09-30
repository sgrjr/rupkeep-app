<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\PricingSetting;
use App\Models\UserLog;
use App\Services\InvoiceLineItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-445. Three ways a rate code mispriced an invoice:
 *
 *  1. "Cancel Without Billing" ran down the ordinary flat branch, so a
 *     no-charge job with tolls, a hotel and wait time billed the expenses.
 *  2. "Flat Price (excludes expenses)" dropped the expenses from the total but
 *     the snapshot still itemized them, so the printed Pilot Car Service line
 *     was the flat MINUS charges the customer was never billed for.
 *  3. A legacy per_mile_rate_X_XX code with a blank rate_value billed $2.00
 *     whatever the code said, and an unknown code silently billed $2.00 too.
 *
 * These run the real job -> invoiceValues() -> InvoiceLineItems path, which is
 * what the printed invoice and the QuickBooks export both read.
 */
class RateCodeExpensePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * A job with one log carrying $20 tolls, a $100 hotel and 2 wait hours
     * (= $60 at the default $30/hr, no free hour).
     */
    private function jobWithExpenses(string $rateCode, ?string $rateValue, array $extra = []): PilotCarJob
    {
        static $seq = 0;
        $seq++;

        $job = PilotCarJob::create(array_merge([
            'job_no' => 'JOB-445-' . $seq,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-445-' . $seq,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => $rateCode,
            'rate_value' => $rateValue,
        ], $extra));

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'billable_miles' => 100,
            'tolls' => '20.00',
            'hotel' => '100.00',
            'wait_time_hours' => 2,
            'started_at' => now()->subDay(),
            'ended_at' => now(),
        ]);

        return $job->fresh();
    }

    private function lineSum(array $values): float
    {
        return round(array_sum(array_column(InvoiceLineItems::build($values), 'amount')), 2);
    }

    public function test_a_no_charge_cancellation_bills_nothing_at_all(): void
    {
        $job = $this->jobWithExpenses('cancel_without_billing', '0.00', [
            'canceled_at' => now(),
            'canceled_reason' => 'Customer canceled',
            'mini_addon_amount' => '75.00',
        ]);

        $values = $job->invoiceValues()['values'];

        $this->assertSame(0.0, (float) $values['total']);
        $this->assertSame('cancel_without_billing', $values['effective_rate_code']);
        $this->assertFalse($values['bills_expenses']);
        $this->assertSame([], InvoiceLineItems::build($values), 'nothing to itemize on a no-charge invoice');

        // The snapshot no longer carries the charges as billable figures ...
        $this->assertSame(0.0, (float) $values['tolls']);
        $this->assertSame(0.0, (float) $values['hotel']);
        $this->assertSame(0.0, (float) $values['cost_of_wait_time']);
        $this->assertSame(0.0, (float) $values['mini_addon_amount']);

        // ... but staff can still see what the driver logged.
        $this->assertEqualsWithDelta(20.0, $values['expenses_not_billed']['tolls'], 0.001);
        $this->assertEqualsWithDelta(100.0, $values['expenses_not_billed']['hotel'], 0.001);
        $this->assertEqualsWithDelta(60.0, $values['expenses_not_billed']['wait_time'], 0.001);
    }

    /** The reproduction from the task: $500 flat, tolls 20, hotel 100, wait 60. */
    public function test_a_flat_price_that_excludes_expenses_prints_only_the_flat_price(): void
    {
        $values = $this->jobWithExpenses('flat_rate_excludes_expenses', '500.00')->invoiceValues()['values'];

        $this->assertSame(500.0, (float) $values['total']);
        $this->assertFalse($values['bills_expenses']);

        $lines = InvoiceLineItems::build($values);

        $this->assertCount(1, $lines, 'only the service line belongs on a flat-only invoice');
        $this->assertSame('pilot_car_service', $lines[0]['key']);
        $this->assertSame(500.0, round($lines[0]['amount'], 2), 'the service line is the flat price, not the flat minus unbilled charges');
        $this->assertSame(500.0, $this->lineSum($values));
    }

    /** The other flavour still adds them on top, and every line reconciles. */
    public function test_a_flat_price_that_bills_expenses_adds_them_on_top(): void
    {
        $values = $this->jobWithExpenses('flat_rate', '500.00')->invoiceValues()['values'];

        $this->assertSame(680.0, (float) $values['total']);
        $this->assertTrue($values['bills_expenses']);
        $this->assertSame([], $values['expenses_not_billed']);

        $keys = array_column(InvoiceLineItems::build($values), 'key');
        $this->assertContains('tolls', $keys);
        $this->assertContains('hotel', $keys);
        $this->assertContains('wait_time', $keys);
        $this->assertSame(680.0, $this->lineSum($values));
    }

    /** Paid cancellation outcomes deliberately bill their expenses on top. */
    public function test_a_show_but_no_go_still_bills_its_expenses(): void
    {
        $values = $this->jobWithExpenses('show_no_go', '225.00', ['canceled_at' => now()])->invoiceValues()['values'];

        $this->assertSame(405.0, (float) $values['total']); // 225 + 20 + 100 + 60
        $this->assertTrue($values['bills_expenses']);
        $this->assertSame(405.0, $this->lineSum($values));
    }

    public function test_a_legacy_per_mile_code_with_no_rate_value_prices_from_the_code(): void
    {
        $values = $this->jobWithExpenses('per_mile_rate_2_50', null)->invoiceValues()['values'];

        $this->assertSame(2.5, (float) $values['effective_rate_value']);
        $this->assertSame(250.0 + 180.0, (float) $values['total']); // 100 mi x $2.50 + expenses
        $this->assertArrayNotHasKey('rate_code_unrecognized', $values);
    }

    /**
     * Nothing to price from: bill the organization's PUBLISHED Lead / Chase
     * rate, never a hardcoded $2.00, and flag the snapshot so the edit screen
     * and jobs:audit can point at it.
     */
    public function test_an_unknown_code_bills_the_published_rate_and_is_flagged(): void
    {
        PricingSetting::setValueForOrganization($this->organization->id, 'rates.lead_chase_per_mile.rate_per_mile', 2.25);

        $values = $this->jobWithExpenses('bogus_code', null)->invoiceValues()['values'];

        $this->assertSame('lead_chase_per_mile', $values['effective_rate_code']);
        $this->assertSame(2.25, (float) $values['effective_rate_value']);
        $this->assertSame(225.0 + 180.0, (float) $values['total']);
        $this->assertSame('bogus_code', $values['rate_code_unrecognized']);
    }

    public function test_a_custom_per_mile_rate_with_no_figure_is_flagged(): void
    {
        $values = $this->jobWithExpenses('new_per_mile_rate', null)->invoiceValues()['values'];

        $this->assertSame('lead_chase_per_mile', $values['effective_rate_code']);
        $this->assertSame('new_per_mile_rate', $values['rate_code_unrecognized']);
    }

    public function test_a_price_list_code_is_never_flagged(): void
    {
        $values = $this->jobWithExpenses('lead_chase_per_mile', null)->invoiceValues()['values'];

        $this->assertArrayNotHasKey('rate_code_unrecognized', $values);
        $this->assertSame(380.0, (float) $values['total']);
    }
}
