<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /invoices — every organization's invoices, for super users. The
 * cross-organization counterpart to /jobs and the twin of /my/invoices.
 *
 * The boundary is the whole point of this suite. /jobs authorizes on viewAny,
 * which any employee passes, and then only filters by organization if the
 * request happens to carry the parameter — so a bare GET /jobs shows every
 * organization's work to anyone signed in. This screen authorizes on a
 * viewAcrossOrganizations ability that only super users hold.
 */
class InvoicesCrossOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $alpha;
    private Organization $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->alpha = Organization::factory()->create(['name' => 'Alpha Escort']);
        $this->beta = Organization::factory()->create(['name' => 'Beta Pilot Cars']);
    }

    private function invoiceFor(Organization $organization, array $values): Invoice
    {
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        return Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'values' => $values,
        ]);
    }

    private function super(): User
    {
        return User::factory()->create([
            'organization_id' => $this->alpha->id,
            'is_super' => true,
        ]);
    }

    public function test_a_super_user_sees_every_organizations_invoices(): void
    {
        $mine = $this->invoiceFor($this->alpha, ['total' => 100]);
        $theirs = $this->invoiceFor($this->beta, ['total' => 900]);

        $response = $this->actingAs($this->super())
            ->get(route('invoices.index'))
            ->assertOk();

        $response->assertSee((string) $mine->fresh()->invoice_number);
        $response->assertSee((string) $theirs->fresh()->invoice_number);

        // Which organization a row belongs to has to be on screen, or the list
        // is a pile of numbers from nowhere.
        $response->assertSee('Beta Pilot Cars');

        $this->assertEqualsWithDelta(1000.0, $response->viewData('listedTotal'), 0.01);
    }

    public function test_an_admin_cannot_reach_the_cross_organization_list(): void
    {
        $this->invoiceFor($this->beta, ['total' => 900]);

        $admin = User::factory()->admin()->create(['organization_id' => $this->alpha->id]);

        $this->actingAs($admin)
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    /**
     * An admin passes viewAny -- they may see their own organization's invoices
     * at /my/invoices. That must not be enough for this screen, which is exactly
     * the conflation /jobs makes.
     */
    public function test_the_own_organization_list_stays_open_to_admins(): void
    {
        $admin = User::factory()->admin()->create(['organization_id' => $this->alpha->id]);

        $this->actingAs($admin)->get(route('my.invoices.index'))->assertOk();
    }

    public function test_a_manager_cannot_reach_it_either(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->alpha->id]);

        $this->actingAs($manager)
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    public function test_a_super_user_can_narrow_to_one_organization(): void
    {
        $mine = $this->invoiceFor($this->alpha, ['total' => 100]);
        $theirs = $this->invoiceFor($this->beta, ['total' => 900]);

        $response = $this->actingAs($this->super())
            ->get(route('invoices.index', ['organization_id' => $this->beta->id]))
            ->assertOk();

        $response->assertSee((string) $theirs->fresh()->invoice_number);
        $response->assertDontSee((string) $mine->fresh()->invoice_number);
    }

    /**
     * Narrowing by organization is a filter on this screen, not the security
     * boundary -- so passing someone else's id at /my/invoices must not widen
     * anything, because there the boundary is the signed-in user's org.
     */
    public function test_the_organization_parameter_does_not_widen_the_scoped_list(): void
    {
        $mine = $this->invoiceFor($this->alpha, ['total' => 100]);
        $theirs = $this->invoiceFor($this->beta, ['total' => 900]);

        $admin = User::factory()->admin()->create(['organization_id' => $this->alpha->id]);

        $response = $this->actingAs($admin)
            ->get(route('my.invoices.index', ['organization_id' => $this->beta->id]))
            ->assertOk();

        $response->assertSee((string) $mine->fresh()->invoice_number);
        $response->assertDontSee((string) $theirs->fresh()->invoice_number);
    }

    /**
     * The reason both screens exist: an invoice with no job is still reachable.
     */
    public function test_orphans_are_listed_across_organizations(): void
    {
        $orphan = $this->invoiceFor($this->beta, ['total' => 400, 'job_no' => 'JOB-LOST']);

        $this->actingAs($this->super())
            ->get(route('invoices.index', ['orphaned' => '1']))
            ->assertOk()
            ->assertSee((string) $orphan->fresh()->invoice_number);
    }
}
