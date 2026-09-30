<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Services\InvoiceLineItems;
use App\Services\InvoiceRepricer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-448. Editing a quantity on the invoice form (tolls, hotel, wait hours,
 * billed deadhead, miles) stored the new quantity and nothing else. The total
 * and the cost lines kept their old figures, so the residual "Pilot Car
 * Service" line moved by the same amount the other way -- tolls 20 -> 120
 * printed a service line $100 lower -- and a big enough edit printed it
 * negative. A hand-typed total did the same thing: the discount vanished into
 * the service line instead of being shown.
 *
 * Now every save that touches a pricing input re-prices the snapshot through
 * the same math that cut the invoice, and a typed total is kept as an explicit
 * Discount / Adjustment line so the lines still sum to the total.
 */
class InvoiceEditRepricingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * A $575 day rate with a $10 toll and a $125 hotel: $710 total, and the
     * service line is exactly the day rate.
     */
    private function dayRateInvoice(): Invoice
    {
        $job = PilotCarJob::create([
            'job_no' => 'JOB-448',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 100,
            'end_mileage' => 400,
            'start_job_mileage' => 100,
            'end_job_mileage' => 300,
            'tolls' => '10.00',
            'hotel' => '125.00',
        ]);

        $invoice = $job->fresh()->createInvoice();

        $this->assertEquals(710.0, (float) data_get($invoice->values, 'total'));

        return $invoice;
    }

    /**
     * Post the edit form the way the browser does: every field is submitted,
     * unchanged ones carrying their stored value.
     */
    private function save(Invoice $invoice, array $changes): Invoice
    {
        $values = $invoice->fresh()->values;
        $posted = [];

        foreach (array_merge(InvoiceRepricer::PRICING_INPUTS, ['dead_head_driven', 'total']) as $key) {
            if (array_key_exists($key, $values)) {
                $posted[$key] = (string) $values[$key];
            }
        }

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $invoice), ['values' => array_merge($posted, $changes)])
            ->assertRedirect(route('my.invoices.edit', $invoice))
            ->assertSessionHas('success');

        return $invoice->fresh();
    }

    private function line(Invoice $invoice, string $key): ?array
    {
        return collect(InvoiceLineItems::build($invoice->values))->firstWhere('key', $key);
    }

    private function assertLinesSumToTotal(Invoice $invoice): void
    {
        $lines = InvoiceLineItems::build($invoice->values);
        $sum = round(array_sum(array_column($lines, 'amount')), 2);

        $this->assertEqualsWithDelta((float) data_get($invoice->values, 'total'), $sum, 0.001, 'Line items must sum to the total.');
    }

    public function test_raising_the_tolls_raises_the_total_and_leaves_the_service_line_alone(): void
    {
        $invoice = $this->dayRateInvoice();

        $invoice = $this->save($invoice, ['tolls' => '110']);

        $this->assertEquals(810.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(575.0, $this->line($invoice, 'pilot_car_service')['amount']);
        $this->assertEquals(110.0, $this->line($invoice, 'tolls')['amount']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_the_service_line_can_no_longer_go_negative(): void
    {
        $invoice = $this->dayRateInvoice();

        $invoice = $this->save($invoice, ['tolls' => '2000']);

        $this->assertEquals(2700.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(575.0, $this->line($invoice, 'pilot_car_service')['amount']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_wait_hours_recompute_the_wait_time_cost(): void
    {
        $invoice = $this->dayRateInvoice();

        // $30 an hour from the first hour: the price list gives none away.
        $invoice = $this->save($invoice, ['wait_time_hours' => '3']);

        $this->assertEquals(90.0, (float) data_get($invoice->values, 'cost_of_wait_time'));
        $this->assertEquals(800.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(90.0, $this->line($invoice, 'wait_time')['amount']);
        $this->assertEquals(3.0, $this->line($invoice, 'wait_time')['quantity']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_billed_deadhead_recomputes_the_deadhead_charge(): void
    {
        $invoice = $this->dayRateInvoice();

        $invoice = $this->save($invoice, ['dead_head_billed' => '40', 'dead_head_driven' => '60']);

        $this->assertEquals(40.0, (float) data_get($invoice->values, 'dead_head_charge'));
        $this->assertEquals(750.0, (float) data_get($invoice->values, 'total'));
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_billable_miles_recompute_the_mileage_charge_on_a_per_mile_job(): void
    {
        $job = PilotCarJob::create([
            'job_no' => 'JOB-448-MILES',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'rate_code' => 'per_mile_rate_2_00',
            'rate_value' => '2.00',
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 100,
        ]);

        $invoice = $job->fresh()->createInvoice();
        $this->assertEquals(200.0, (float) data_get($invoice->values, 'total'));

        $invoice = $this->save($invoice, ['billable_miles' => '150']);

        $this->assertEquals(300.0, (float) data_get($invoice->values, 'cost_for_mileage'));
        $this->assertEquals(300.0, (float) data_get($invoice->values, 'total'));
        $this->assertNull($this->line($invoice, 'pilot_car_service'), 'A per-mile job has no separate service line.');
        $this->assertEquals(150.0, $this->line($invoice, 'mileage')['quantity']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_a_typed_total_becomes_a_discount_line_rather_than_shrinking_the_service_line(): void
    {
        $invoice = $this->dayRateInvoice();

        $invoice = $this->save($invoice, ['total' => '610']);

        $this->assertEquals(610.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(-100.0, (float) data_get($invoice->values, 'adjustment'));
        $this->assertEquals(575.0, $this->line($invoice, 'pilot_car_service')['amount']);

        $discount = $this->line($invoice, 'adjustment');
        $this->assertSame('Discount', $discount['description']);
        $this->assertEquals(-100.0, $discount['amount']);
        $this->assertLinesSumToTotal($invoice);

        // The customer sees the discount, not a smaller service charge.
        $html = $this->actingAs($this->admin)->get(route('my.invoices.print', $invoice))->assertOk()->getContent();
        $this->assertStringContainsString('Discount', $html);
        $this->assertStringContainsString('-$100.00', $html);
        $this->assertStringContainsString('$575.00', $html);
    }

    public function test_a_typed_total_above_the_math_is_an_adjustment_line(): void
    {
        $invoice = $this->dayRateInvoice();

        $invoice = $this->save($invoice, ['total' => '760']);

        $this->assertEquals(50.0, (float) data_get($invoice->values, 'adjustment'));
        $this->assertSame('Adjustment', $this->line($invoice, 'adjustment')['description']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_a_discount_survives_a_later_quantity_edit(): void
    {
        $invoice = $this->dayRateInvoice();
        $invoice = $this->save($invoice, ['total' => '610']);

        // Correct the tolls; the total field is posted unchanged at 610.
        $invoice = $this->save($invoice, ['tolls' => '20']);

        $this->assertEquals(-100.0, (float) data_get($invoice->values, 'adjustment'));
        $this->assertEquals(620.0, (float) data_get($invoice->values, 'total'), '575 + 20 + 125 - 100');
        $this->assertEquals(575.0, $this->line($invoice, 'pilot_car_service')['amount']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_typing_the_computed_total_back_clears_the_discount(): void
    {
        $invoice = $this->dayRateInvoice();
        $invoice = $this->save($invoice, ['total' => '610']);

        $invoice = $this->save($invoice, ['total' => '710']);

        $this->assertArrayNotHasKey('adjustment', $invoice->values);
        $this->assertEquals(710.0, (float) data_get($invoice->values, 'total'));
        $this->assertNull($this->line($invoice, 'adjustment'));
    }

    public function test_the_success_message_says_what_the_total_became(): void
    {
        $invoice = $this->dayRateInvoice();

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $invoice), ['values' => ['tolls' => '110', 'total' => '710']])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, '$810.00'));

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $invoice), ['values' => ['tolls' => '110', 'total' => '700']])
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'discount of $110.00'));
    }

    public function test_editing_only_the_billing_details_leaves_the_money_alone(): void
    {
        // An imported historical invoice: a total and nothing to compute it from.
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'values' => ['title' => 'INVOICE', 'total' => 999.0, 'bill_to' => ['company' => 'Old Co']],
        ]);

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $invoice), [
                'values' => ['total' => '999', 'bill_to' => ['company' => 'New Co'], 'notes' => 'Net 30'],
            ])
            ->assertSessionHas('success', 'Invoice updated.');

        $invoice->refresh();

        $this->assertEquals(999.0, (float) data_get($invoice->values, 'total'));
        $this->assertArrayNotHasKey('adjustment', $invoice->values);
        $this->assertArrayNotHasKey('cost_for_mileage', $invoice->values);
        $this->assertSame('New Co', data_get($invoice->values, 'bill_to.company'));
    }

    public function test_an_invoice_whose_job_is_gone_still_reprices(): void
    {
        $invoice = $this->dayRateInvoice();
        $invoice->job->forceDelete();
        $invoice = $invoice->fresh();
        $this->assertNull($invoice->job);

        $invoice = $this->save($invoice, ['tolls' => '30']);

        $this->assertEquals(730.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(575.0, $this->line($invoice, 'pilot_car_service')['amount']);
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_a_flat_rate_that_excludes_expenses_keeps_ignoring_them_after_an_edit(): void
    {
        $job = PilotCarJob::create([
            'job_no' => 'JOB-448-EXCL',
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'rate_code' => 'flat_rate_excludes_expenses',
            'rate_value' => '500.00',
        ]);

        UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 50,
            'start_job_mileage' => 0,
            'end_job_mileage' => 50,
            'tolls' => '10.00',
            'hotel' => '125.00',
        ]);

        $invoice = $job->fresh()->createInvoice();
        $this->assertEquals(500.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(125.0, (float) data_get($invoice->values, 'expenses_not_billed.hotel'));

        // Wait hours are an expense too; this rate does not bill them.
        $invoice = $this->save($invoice, ['wait_time_hours' => '4']);

        $this->assertEquals(500.0, (float) data_get($invoice->values, 'total'));
        $this->assertEquals(0.0, (float) data_get($invoice->values, 'tolls'));
        $this->assertEquals(125.0, (float) data_get($invoice->values, 'expenses_not_billed.hotel'), 'The logged hotel is still on record for staff.');
        $this->assertEquals(120.0, (float) data_get($invoice->values, 'expenses_not_billed.wait_time'));
        $this->assertLinesSumToTotal($invoice);
    }

    public function test_a_summary_total_is_not_repriced(): void
    {
        $summary = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'invoice_type' => 'summary',
            'values' => ['title' => 'INVOICE', 'total' => 1000.0, 'billable_miles' => 100],
        ]);

        $this->actingAs($this->admin)
            ->put(route('my.invoices.update', $summary), ['values' => ['total' => '900', 'billable_miles' => '100']])
            ->assertSessionHas('success');

        $summary->refresh();
        $this->assertEquals(900.0, (float) data_get($summary->values, 'total'));
        $this->assertArrayNotHasKey('adjustment', $summary->values);
    }

    public function test_the_edit_form_no_longer_offers_the_effective_rate_for_editing(): void
    {
        $invoice = $this->dayRateInvoice();

        $html = $this->actingAs($this->admin)->get(route('my.invoices.edit', $invoice))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="values[effective_rate_code]"', $html);
        $this->assertStringNotContainsString('name="values[effective_rate_value]"', $html);
        $this->assertStringContainsString('name="values[total]"', $html);
        $this->assertStringContainsString('Recalculated from the figures above', $html);
    }

    public function test_the_edit_page_shows_the_discount_beside_the_subtotal(): void
    {
        $invoice = $this->dayRateInvoice();
        $invoice = $this->save($invoice, ['total' => '610']);

        $html = $this->actingAs($this->admin)->get(route('my.invoices.edit', $invoice))->assertOk()->getContent();

        $this->assertStringContainsString('-$100.00', $html);
        $this->assertStringContainsString('shown as its own line', $html);
    }
}
