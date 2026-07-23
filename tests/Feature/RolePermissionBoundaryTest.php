<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * Role boundary on staff-only surfaces (TASK-358).
 *
 * Customer-portal accounts carry the pilot-car company's organization_id, so
 * org scoping alone does not keep them off staff surfaces. The staff listing
 * routes (job list, vehicle fleet, customer roster) were gated only by
 * auth+verified, so a customer-role user got HTTP 200 and saw the whole roster.
 *
 * The fix gates those routes behind the `staff` middleware: customers are
 * redirected to their portal home, guests are refused, and every staff role
 * (admin / manager / standard driver) keeps its existing access.
 */
class RolePermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /** TASK-358 */
    public function test_customer_role_user_cannot_open_the_job_list(): void
    {
        $org = $this->createOrganization('Org A');
        $customer = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customer)
            ->get(route('my.jobs.index'))
            ->assertRedirect(route('customer.invoices.index'));
    }

    /** TASK-358 */
    public function test_customer_role_user_cannot_open_the_vehicle_fleet(): void
    {
        $org = $this->createOrganization('Org A');
        $customer = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customer)
            ->get(route('my.vehicles.index'))
            ->assertRedirect(route('customer.invoices.index'));
    }

    /** TASK-358 */
    public function test_customer_role_user_cannot_open_the_customer_roster(): void
    {
        $org = $this->createOrganization('Org A');
        $customer = $this->createUserForOrganization($org, User::ROLE_CUSTOMER);

        $this->actingAs($customer)
            ->get(route('customers.index'))
            ->assertRedirect(route('customer.invoices.index'));
    }

    /**
     * A guest-role account (no portal, no staff role) is refused outright
     * rather than redirected.
     */
    public function test_guest_role_user_is_forbidden_from_staff_listings(): void
    {
        $org = $this->createOrganization('Org A');
        $guest = $this->createUserForOrganization($org, User::ROLE_GUEST);

        $this->actingAs($guest)
            ->get(route('my.jobs.index'))
            ->assertForbidden();
    }

    /**
     * Positive control: managers keep full access to staff listings — this also
     * protects the post-login redirect that sends managers to my.jobs.index.
     */
    public function test_manager_keeps_access_to_staff_listings(): void
    {
        $org = $this->createOrganization('Org A');
        $manager = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_MANAGER);

        $this->actingAs($manager)->get(route('my.jobs.index'))->assertOk();
        $this->actingAs($manager)->get(route('my.vehicles.index'))->assertOk();
        $this->actingAs($manager)->get(route('customers.index'))->assertOk();
    }

    /**
     * Positive control: standard-role drivers are staff and must retain access.
     */
    public function test_standard_driver_keeps_access_to_staff_listings(): void
    {
        $org = $this->createOrganization('Org A');
        $driver = $this->createUserForOrganization($org, User::ROLE_EMPLOYEE_STANDARD);

        $this->actingAs($driver)->get(route('my.jobs.index'))->assertOk();
        $this->actingAs($driver)->get(route('my.vehicles.index'))->assertOk();
    }
}
