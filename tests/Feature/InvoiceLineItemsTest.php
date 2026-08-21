<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-367. Reported against job 1023: a $575 day-rate job with a hotel and a
 * $10 toll showed only the toll as an itemized charge, with the hotel silently
 * absorbed into the catch-all "Pilot Car Service" line.
 *
 * Same class of bug as TASK-365 — the template derives Pilot Car Service as
 * (total − everything itemized), so any expense missing from that subtraction
 * disappears into it. hotel and extra_charge were both missing.
 */
class InvoiceLineItemsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
    }

    /**
     * Mirrors the reported job: flat day rate, a hotel stay and a toll.
     */
    private function dayRateJobWithHotel(array $logAttributes = []): PilotCarJob
    {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $job = PilotCarJob::create([
            'job_no' => 'JOB-DAYRATE',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Main Street, Portland ME',
            'delivery_address' => 'Boston MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 123,
            'end_mileage' => 400,
            'start_job_mileage' => 123,
            'end_job_mileage' => 324,
            'tolls' => '10.00',
            'hotel' => '125.00',
        ], $logAttributes));

        return $job->fresh();
    }

    private function renderInvoice(PilotCarJob $job): string
    {
        $invoice = $job->createInvoice();
        $manager = User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($manager)->get(route('my.invoices.print', $invoice));
        $response->assertOk();

        return $response->getContent();
    }

    public function test_the_total_is_the_rate_plus_its_expenses(): void
    {
        $values = $this->dayRateJobWithHotel()->invoiceValues()['values'];

        // 575 flat + 10 tolls + 125 hotel
        $this->assertSame(710.0, (float) $values['total']);
    }

    public function test_hotel_gets_its_own_line_item(): void
    {
        $html = $this->renderInvoice($this->dayRateJobWithHotel());

        $this->assertStringContainsString('Overnight / Hotel', $html);
        $this->assertStringContainsString('$125.00', $html);
    }

    public function test_extra_charges_get_their_own_line_item(): void
    {
        $html = $this->renderInvoice($this->dayRateJobWithHotel(['extra_charge' => '45.00']));

        $this->assertStringContainsString('Extra Charges', $html);
        $this->assertStringContainsString('$45.00', $html);
    }

    public function test_pilot_car_service_is_only_the_rate_charge(): void
    {
        $html = $this->renderInvoice($this->dayRateJobWithHotel());

        // The reported symptom was this line reading 760 (i.e. total minus only
        // the toll) instead of the 575 actually being charged for the service.
        $this->assertStringContainsString('$575.00', $html);
        $this->assertStringNotContainsString('$700.00', $html);
    }

    public function test_zero_value_charges_are_not_rendered(): void
    {
        $html = $this->renderInvoice($this->dayRateJobWithHotel());

        // Nothing was logged for these, so they have no business on the invoice.
        $this->assertStringNotContainsString('Wait Time', $html);
        $this->assertStringNotContainsString('Extra Stops', $html);
        $this->assertStringNotContainsString('Dead Head Miles', $html);
    }

    public function test_a_charge_that_does_have_a_value_still_renders(): void
    {
        $html = $this->renderInvoice($this->dayRateJobWithHotel([
            'extra_load_stops_count' => 2,
            'wait_time_hours' => 3,
        ]));

        $this->assertStringContainsString('Extra Stops', $html);
        $this->assertStringContainsString('Wait Time', $html);
    }

    public function test_fractional_quantities_are_not_rounded_away(): void
    {
        $html = $this->renderInvoice($this->dayRateJobWithHotel(['wait_time_hours' => 2.5]));

        // 2.5 hrs must not render as "3".
        $this->assertStringContainsString('2.5', $html);
    }

    public function test_every_rendered_line_item_sums_to_the_total(): void
    {
        $job = $this->dayRateJobWithHotel([
            'extra_load_stops_count' => 1,
            'wait_time_hours' => 2,
            'extra_charge' => '45.00',
        ]);

        $values = $job->invoiceValues()['values'];

        $rate = (float) $values['total']
            - (float) $values['tolls']
            - (float) $values['hotel']
            - (float) $values['extra_charge']
            - (float) $values['cost_of_wait_time']
            - ((float) $values['extra_load_stops_count'] * (float) $values['cost_of_extra_stop'])
            - (float) $values['cost_for_mileage'];

        // Whatever is left over after every itemized charge IS the flat rate.
        $this->assertEqualsWithDelta(575.0, $rate, 0.01);
    }
}
