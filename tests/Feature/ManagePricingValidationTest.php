<?php

namespace Tests\Feature;

use App\Livewire\ManagePricing;
use App\Models\Organization;
use App\Models\PricingSetting;
use App\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-452. The pricing page wrote whatever it was handed: 'abc' stored as
 * 0, '-5' as -5 (a negative wait rate turned wait time into a credit), and
 * any code or field name at all went straight into the settings table and
 * from there into invoice math. Now every write is checked against the price
 * list and a per-field rule, and a refusal is shown beside the field.
 */
class ManagePricingValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function page()
    {
        return Livewire::actingAs($this->admin)->test(ManagePricing::class);
    }

    private function assertNothingStored(): void
    {
        $this->assertSame(0, PricingSetting::where('organization_id', $this->organization->id)->count());
    }

    public function test_a_negative_rate_is_refused_and_shown(): void
    {
        $this->page()
            ->call('updateCharge', 'wait_time', 'rate_per_hour', '-5')
            ->assertHasErrors(['charges.wait_time.rate_per_hour'])
            ->assertSee('Not saved');

        $this->assertNothingStored();
        $this->assertEquals(30.0, (float) PricingResolver::charges($this->organization->id)['wait_time']['rate_per_hour']);
    }

    public function test_text_in_a_money_field_is_refused(): void
    {
        $this->page()
            ->call('updateRate', 'lead_chase_per_mile', 'rate_per_mile', 'abc')
            ->assertHasErrors(['rates.lead_chase_per_mile.rate_per_mile'])
            ->assertSee('must be a number');

        $this->assertNothingStored();
    }

    public function test_a_code_that_is_not_on_the_price_list_is_refused(): void
    {
        $this->page()
            ->call('updateRate', 'made_up_rate', 'flat_amount', '100')
            ->assertHasErrors(['rates.made_up_rate.flat_amount']);

        $this->page()
            ->call('updateCharge', 'made_up_charge', 'rate_per_hour', '10')
            ->assertHasErrors(['charges.made_up_charge.rate_per_hour']);

        $this->assertNothingStored();
    }

    public function test_a_field_the_entry_does_not_have_is_refused(): void
    {
        // A per-mile rate has no flat amount; tolls are a reimbursement with no hourly rate.
        $this->page()
            ->call('updateRate', 'lead_chase_per_mile', 'flat_amount', '100')
            ->assertHasErrors(['rates.lead_chase_per_mile.flat_amount']);

        $this->page()
            ->call('updateCharge', 'tolls', 'rate_per_hour', '5')
            ->assertHasErrors(['charges.tolls.rate_per_hour']);

        $this->page()
            ->call('updatePaymentTerms', 'secret_field', '1')
            ->assertHasErrors(['payment_terms.secret_field']);

        $this->page()
            ->call('updateCancellation', 'whatever', '1')
            ->assertHasErrors(['cancellation.whatever']);

        $this->assertNothingStored();
    }

    public function test_out_of_range_values_are_refused(): void
    {
        $this->page()
            ->call('updatePaymentTerms', 'late_fee_percentage', '150')
            ->assertHasErrors(['payment_terms.late_fee_percentage']);

        $this->page()
            ->call('updatePaymentTerms', 'late_fee_period_days', '0')
            ->assertHasErrors(['payment_terms.late_fee_period_days']);

        $this->page()
            ->call('updateRate', 'mini_flat_rate', 'max_miles', '12.5')
            ->assertHasErrors(['rates.mini_flat_rate.max_miles']);

        $this->assertNothingStored();
    }

    public function test_a_valid_value_is_stored_and_a_blank_reverts_it(): void
    {
        $page = $this->page()
            ->call('updateRate', 'lead_chase_per_mile', 'rate_per_mile', '2.75')
            ->assertHasNoErrors();

        $this->assertEquals(2.75, (float) PricingResolver::rates($this->organization->id)['lead_chase_per_mile']['rate_per_mile']);

        $page->call('updateCharge', 'wait_time', 'rate_per_hour', '35')
            ->call('updatePaymentTerms', 'late_fee_percentage', '5')
            ->call('updateCancellation', 'hours_before_pickup_for_24hr_charge', '48')
            ->assertHasNoErrors();

        $this->assertEquals(35.0, (float) PricingResolver::charges($this->organization->id)['wait_time']['rate_per_hour']);
        $this->assertEquals(5.0, (float) PricingResolver::paymentTerms($this->organization->id)['late_fee_percentage']);
        $this->assertEquals(48, (int) PricingResolver::cancellation($this->organization->id)['hours_before_pickup_for_24hr_charge']);

        $page->call('updateRate', 'lead_chase_per_mile', 'rate_per_mile', '')->assertHasNoErrors();

        $this->assertEquals(
            config('pricing.rates.lead_chase_per_mile.rate_per_mile'),
            (float) PricingResolver::rates($this->organization->id)['lead_chase_per_mile']['rate_per_mile']
        );
    }

    public function test_a_custom_charge_takes_its_own_unit_and_amount_but_a_standard_one_does_not(): void
    {
        $page = $this->page()
            ->set('newCharge.name', 'Permit Escort')
            ->set('newCharge.unit', 'per_hour')
            ->set('newCharge.amount', '45')
            ->call('addCharge')
            ->assertHasNoErrors();

        $page->call('updateCharge', 'permit_escort', 'unit', 'flat')
            ->call('updateCharge', 'permit_escort', 'flat_amount', '300')
            ->assertHasNoErrors();

        $page->call('updateCharge', 'permit_escort', 'unit', 'per_lightyear')
            ->assertHasErrors(['charges.permit_escort.unit']);

        $page->call('updateCharge', 'wait_time', 'unit', 'flat')
            ->assertHasErrors(['charges.wait_time.unit']);
    }

    public function test_the_setting_model_refuses_a_non_numeric_number_on_its_own(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PricingSetting::setValueForOrganization($this->organization->id, 'charges.wait_time.rate_per_hour', 'abc', 'float', 'charges');
    }

    public function test_the_setting_model_refuses_a_negative_number_on_its_own(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PricingSetting::setValueForOrganization($this->organization->id, 'charges.wait_time.rate_per_hour', -5, 'float', 'charges');
    }
}
