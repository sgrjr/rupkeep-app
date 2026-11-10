<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-504 (drive 003). The customer directory had no way to search.
 */
class CustomerDirectorySearchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->organization = Organization::factory()->create();
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);

        $jls = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'JLS Pilot Car', 'city' => 'Sugar Valley']);
        CustomerContact::create(['customer_id' => $jls->id, 'organization_id' => $this->organization->id, 'name' => 'Audra Smith', 'email' => 'audra@jls.example', 'phone' => '207-555-0100']);

        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Casco Bay Transportation', 'city' => 'Portland']);

        // Another organization's customer never shows, searched or not.
        Customer::factory()->create(['organization_id' => Organization::factory()->create()->id, 'name' => 'JLS Elsewhere']);
    }

    private function search(string $q)
    {
        return $this->actingAs($this->manager)->get(route('my.customers.index', ['q' => $q]));
    }

    public function test_it_matches_name_city_and_contact_details(): void
    {
        foreach (['jls', 'sugar valley', 'audra', 'AUDRA@JLS', '2075550100', '555-0100'] as $q) {
            $this->search($q)
                ->assertOk()
                ->assertViewHas('listedCustomers', fn ($list) => $list->pluck('name')->all() === ['JLS Pilot Car'])
                ->assertSee('value="'.e($q).'"', false);
        }
    }

    public function test_the_tiles_still_count_every_customer_and_no_match_says_so(): void
    {
        $this->search('nobody-by-this-name')
            ->assertOk()
            ->assertViewHas('listedCustomers', fn ($list) => $list->isEmpty())
            ->assertViewHas('allCustomers', fn ($all) => $all->count() === 2)
            ->assertSee('No customers match your search.');
    }

    public function test_no_query_lists_everyone_in_the_organization(): void
    {
        $this->actingAs($this->manager)->get(route('my.customers.index'))
            ->assertOk()
            ->assertViewHas('listedCustomers', fn ($list) => $list->count() === 2)
            ->assertDontSee('JLS Elsewhere');
    }
}
