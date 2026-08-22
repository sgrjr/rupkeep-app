<?php

namespace Tests\Feature;

use App\Livewire\ManagePricing;
use App\Models\Organization;
use App\Models\PricingSetting;
use App\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-377: an org admin can extend the canonical price list at /my/pricing.
 *
 * The set of charges used to be fixed by the keys in config/pricing.php, so
 * putting an "Overnight / Hotel" row on the price sheet meant a code change and
 * a deploy.
 */
class CustomPricingChargeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Organization $org): User
    {
        return User::factory()->admin()->create(['organization_id' => $org->id]);
    }

    public function test_admin_adds_a_per_unit_charge_and_it_publishes_to_the_public_page(): void
    {
        $org = Organization::factory()->create();
        Config::set('pricing.default_organization_id', $org->id);

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', [
                'name' => 'Permit Escort',
                'description' => 'Runs the permit ahead of the load',
                'unit' => 'per_hour',
                'amount' => '45.50',
            ])
            ->call('addCharge')
            ->assertHasNoErrors();

        $charges = PricingResolver::charges($org->id);

        $this->assertArrayHasKey('permit_escort', $charges);
        $this->assertSame('Permit Escort', $charges['permit_escort']['name']);
        $this->assertSame(45.50, $charges['permit_escort']['rate_per_hour']);
        $this->assertTrue($charges['permit_escort']['is_custom']);

        // The config-backed charges are still all there.
        foreach (array_keys(config('pricing.charges')) as $configKey) {
            $this->assertArrayHasKey($configKey, $charges);
            $this->assertFalse($charges[$configKey]['is_custom']);
        }

        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('Permit Escort')
            ->assertSee('Runs the permit ahead of the load')
            ->assertSee('$45.50');
    }

    public function test_an_info_only_charge_publishes_its_words_and_no_number(): void
    {
        $org = Organization::factory()->create();
        Config::set('pricing.default_organization_id', $org->id);

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', [
                'name' => 'Ferry Fares',
                'description' => 'Billed at cost',
                'unit' => 'none',
                'amount' => '',
            ])
            ->call('addCharge')
            ->assertHasNoErrors();

        $charge = PricingResolver::charges($org->id)['ferry_fares'];

        $this->assertSame('none', $charge['unit']);
        foreach (PricingResolver::CHARGE_NUMERIC_FIELDS as $field) {
            $this->assertNull($charge[$field]);
        }

        $this->get(route('pricing'))->assertOk()->assertSee('Billed at cost');
    }

    public function test_a_flat_charge_prints_its_amount_on_the_public_page(): void
    {
        $org = Organization::factory()->create();
        Config::set('pricing.default_organization_id', $org->id);

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Overnight Hotel', 'description' => '', 'unit' => 'flat', 'amount' => '175'])
            ->call('addCharge');

        $this->assertSame(175.0, PricingResolver::charges($org->id)['overnight_hotel_2']['flat_amount']);

        $this->get(route('pricing'))->assertOk()->assertSee('$175.00');
    }

    public function test_a_new_charge_needs_a_name_and_needs_an_amount_when_it_has_a_unit(): void
    {
        $org = Organization::factory()->create();

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => '', 'description' => '', 'unit' => 'none', 'amount' => ''])
            ->call('addCharge')
            ->assertHasErrors(['newCharge.name' => 'required']);

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Standby', 'description' => '', 'unit' => 'per_hour', 'amount' => ''])
            ->call('addCharge')
            ->assertHasErrors(['newCharge.amount' => 'required']);

        $this->assertSame([], PricingResolver::customChargeKeys($org->id));
    }

    public function test_a_second_charge_of_the_same_name_gets_its_own_key(): void
    {
        $org = Organization::factory()->create();
        $admin = $this->admin($org);

        Livewire::actingAs($admin)->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Standby', 'description' => '', 'unit' => 'none', 'amount' => ''])
            ->call('addCharge')
            ->set('newCharge', ['name' => 'Standby', 'description' => '', 'unit' => 'none', 'amount' => ''])
            ->call('addCharge');

        $this->assertSame(['standby', 'standby_2'], PricingResolver::customChargeKeys($org->id));
    }

    public function test_a_name_that_collides_with_a_config_charge_gets_its_own_key(): void
    {
        $org = Organization::factory()->create();

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Dead Head', 'description' => '', 'unit' => 'none', 'amount' => ''])
            ->call('addCharge');

        // config/pricing.php already owns `dead_head`; the custom entry must not
        // shadow it, or an org would overwrite a charge invoice math can read.
        $this->assertSame(['dead_head_2'], PricingResolver::customChargeKeys($org->id));
        $this->assertSame('Dead Head Miles', PricingResolver::charges($org->id)['dead_head']['name']);
    }

    public function test_removing_a_custom_charge_takes_it_off_the_public_page(): void
    {
        $org = Organization::factory()->create();
        Config::set('pricing.default_organization_id', $org->id);

        $component = Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Permit Escort', 'description' => '', 'unit' => 'per_hour', 'amount' => '45'])
            ->call('addCharge');

        $this->get(route('pricing'))->assertSee('Permit Escort');

        $component->call('removeCharge', 'permit_escort');

        $this->assertSame([], PricingResolver::customChargeKeys($org->id));
        $this->assertArrayNotHasKey('permit_escort', PricingResolver::charges($org->id));
        $this->assertDatabaseMissing('pricing_settings', [
            'organization_id' => $org->id,
            'setting_key' => 'charges.permit_escort.rate_per_hour',
        ]);
        $this->assertDatabaseMissing('pricing_settings', [
            'organization_id' => $org->id,
            'setting_key' => PricingResolver::CUSTOM_CHARGES_KEY,
        ]);

        $this->get(route('pricing'))->assertDontSee('Permit Escort');
    }

    public function test_a_config_backed_charge_cannot_be_removed(): void
    {
        $org = Organization::factory()->create();

        $component = Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->call('removeCharge', 'wait_time');

        $this->assertSame(
            config('pricing.charges.wait_time.name'),
            $component->get('charges.wait_time.name')
        );
        $this->assertArrayHasKey('wait_time', PricingResolver::charges($org->id));
    }

    public function test_removing_a_charge_leaves_another_orgs_identical_key_alone(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();

        foreach ([$mine, $theirs] as $org) {
            Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
                ->set('newCharge', ['name' => 'Permit Escort', 'description' => '', 'unit' => 'per_hour', 'amount' => '45'])
                ->call('addCharge');
        }

        Livewire::actingAs($this->admin($mine))->test(ManagePricing::class)
            ->call('removeCharge', 'permit_escort');

        $this->assertSame([], PricingResolver::customChargeKeys($mine->id));
        $this->assertSame(45.0, PricingResolver::charges($theirs->id)['permit_escort']['rate_per_hour']);
    }

    public function test_changing_the_unit_swaps_which_amount_is_quoted(): void
    {
        $org = Organization::factory()->create();

        $component = Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Permit Escort', 'description' => '', 'unit' => 'per_hour', 'amount' => '45'])
            ->call('addCharge')
            ->call('updateCharge', 'permit_escort', 'unit', 'flat')
            ->call('updateCharge', 'permit_escort', 'flat_amount', '300');

        $charge = PricingResolver::charges($org->id)['permit_escort'];

        $this->assertSame(300.0, $charge['flat_amount']);
        $this->assertNull($charge['rate_per_hour'], 'the superseded per-hour rate must not still publish');

        // Switching back restores the original number rather than losing it.
        $component->call('updateCharge', 'permit_escort', 'unit', 'per_hour');
        $this->assertSame(45.0, PricingResolver::charges($org->id)['permit_escort']['rate_per_hour']);
    }

    public function test_a_custom_charge_cannot_be_left_unnamed(): void
    {
        $org = Organization::factory()->create();

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('newCharge', ['name' => 'Permit Escort', 'description' => '', 'unit' => 'none', 'amount' => ''])
            ->call('addCharge')
            ->call('updateCharge', 'permit_escort', 'name', '');

        // Blank reverts a config charge to its default; a custom one has no
        // default, so the name stands.
        $this->assertSame('Permit Escort', PricingResolver::charges($org->id)['permit_escort']['name']);
    }

    public function test_a_config_charge_still_reverts_to_its_default_when_blanked(): void
    {
        $org = Organization::factory()->create();

        $component = Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->call('updateCharge', 'tolls', 'name', 'Turnpike Fees');

        $this->assertSame('Turnpike Fees', $component->get('charges.tolls.name'));

        $component->call('updateCharge', 'tolls', 'name', '');

        $this->assertSame(config('pricing.charges.tolls.name'), $component->get('charges.tolls.name'));
        $this->assertDatabaseMissing('pricing_settings', [
            'organization_id' => $org->id,
            'setting_key' => 'charges.tolls.name',
        ]);
    }

    public function test_the_charges_tab_renders_the_add_form_and_marks_custom_entries(): void
    {
        $org = Organization::factory()->create();

        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('activeTab', 'charges')
            ->set('newCharge', ['name' => 'Permit Escort', 'description' => '', 'unit' => 'per_hour', 'amount' => '45'])
            ->call('addCharge')
            ->assertSee('Add a charge')
            ->assertSee('Add to price list')
            ->assertSee('Added by you')
            ->assertSee('Permit Escort')
            // The standard entries keep their config-driven fields.
            ->assertSee('Free Miles')
            ->assertSee('Minimum Hours');
    }

    public function test_a_corrupt_registry_row_does_not_take_the_pricing_pages_down(): void
    {
        $org = Organization::factory()->create();
        Config::set('pricing.default_organization_id', $org->id);

        PricingSetting::setValueForOrganization(
            $org->id,
            PricingResolver::CUSTOM_CHARGES_KEY,
            ['ghost_charge'],
            'json',
            'charges'
        );

        // The registry names a key with no rows behind it. It resolves to an
        // empty entry rather than a fatal on an unguarded array read.
        $charge = PricingResolver::charges($org->id)['ghost_charge'];
        $this->assertSame('', $charge['name']);
        $this->assertSame('none', $charge['unit']);

        $this->get(route('pricing'))->assertOk();
        Livewire::actingAs($this->admin($org))->test(ManagePricing::class)
            ->set('activeTab', 'charges')
            ->assertOk();
    }
}
