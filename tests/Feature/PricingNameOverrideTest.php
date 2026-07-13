<?php

namespace Tests\Feature;

use App\Livewire\ManagePricing;
use App\Models\Organization;
use App\Models\PricingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class PricingNameOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_admin_can_override_and_revert_a_rate_name_and_description(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $rateCode = array_key_first(config('pricing.rates'));
        $configName = config("pricing.rates.{$rateCode}.name");

        $component = Livewire::actingAs($admin)->test(ManagePricing::class)
            ->call('updateRate', $rateCode, 'name', 'Wait Time / Over 8 Hr.')
            ->call('updateRate', $rateCode, 'description', 'FULL TOLL REIMBURSEMENT');

        $this->assertSame('Wait Time / Over 8 Hr.', $component->get("rates.{$rateCode}.name"));
        $this->assertSame('FULL TOLL REIMBURSEMENT', $component->get("rates.{$rateCode}.description"));
        $this->assertSame(
            'Wait Time / Over 8 Hr.',
            PricingSetting::getValueForOrganization($org->id, "rates.{$rateCode}.name")
        );

        // Blank reverts to the config default (setting row deleted).
        $component->call('updateRate', $rateCode, 'name', '');

        $this->assertSame($configName, $component->get("rates.{$rateCode}.name"));
        $this->assertDatabaseMissing('pricing_settings', [
            'organization_id' => $org->id,
            'setting_key' => "rates.{$rateCode}.name",
        ]);
    }

    public function test_public_pricing_page_reflects_the_org_name_override(): void
    {
        $org = Organization::factory()->create();

        $chargeKey = array_key_first(config('pricing.charges'));
        PricingSetting::setValueForOrganization(
            $org->id,
            "charges.{$chargeKey}.name",
            'Renamed On Public Page',
            'string',
            'charges'
        );

        // Make the public /pricing page resolve to this organization.
        Config::set('pricing.default_organization_id', $org->id);

        $this->get(route('pricing'))
            ->assertOk()
            ->assertSee('Renamed On Public Page');
    }
}
