<?php

namespace Tests\Feature;

use App\Livewire\OrganizationShow;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\CreatesMultiTenantFixtures;
use Tests\TestCase;

/**
 * TASK-429 / TASK-391 regression. OrganizationShow::mount() authorized only
 * `view` (any same-org role) and none of the public actions checked anything,
 * so a customer-portal user could mint an admin, wipe every job, user,
 * vehicle and customer, and import jobs (Poc2Test proved it). A Livewire
 * action is its own endpoint; every one now authorizes on its own.
 */
class OrganizationShowAuthorizationTest extends TestCase
{
    use RefreshDatabase, CreatesMultiTenantFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function open(User $as, int $organizationId)
    {
        return Livewire::actingAs($as)->test(OrganizationShow::class, ['organization' => $organizationId]);
    }

    public function test_customer_portal_user_cannot_open_the_page(): void
    {
        $a = $this->createOrganization('A');
        $customer = $this->createUserForOrganization($a, User::ROLE_CUSTOMER);

        // HTTP first: a 403 inside a Livewire mount leaves Livewire's redirector
        // bound in the test app, which then breaks the middleware's redirect().
        $this->actingAs($customer)->get(route('organizations.show', $a))->assertRedirect(route('customer.invoices.index'));
        $this->open($customer, $a->id)->assertForbidden();
    }

    public function test_staff_of_another_organization_cannot_open_the_page(): void
    {
        $a = $this->createOrganization('A');
        $b = $this->createOrganization('B');
        $adminA = $this->createUserForOrganization($a, User::ROLE_ADMIN);

        $this->open($adminA, $b->id)->assertForbidden();
    }

    public function test_driver_can_view_but_cannot_act(): void
    {
        $a = $this->createOrganization('A');
        $driver = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_STANDARD);
        $job = $this->createJobForOrganization($a);

        $this->open($driver, $a->id)->assertOk();

        // One component per call: a 403 leaves a test instance without a snapshot.
        foreach (['deleteJobs', 'deleteInvoices', 'deleteUsers', 'deleteVehicles', 'deleteCustomers', 'previewHeaders', 'confirmImport', 'uploadFile'] as $action) {
            $this->open($driver, $a->id)->call($action)->assertForbidden();
        }

        $this->open($driver, $a->id)
            ->set('form.name', 'Evil')
            ->set('form.email', 'evil@example.test')
            ->set('form.password', 'secret1')
            ->set('form.password_confirmation', 'secret1')
            ->set('form.role', User::ROLE_ADMIN)
            ->call('createUser')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'evil@example.test']);
        $this->assertNotNull(PilotCarJob::find($job->id));
    }

    public function test_org_admin_can_create_a_validated_user_and_reset_their_own_org(): void
    {
        $a = $this->createOrganization('A');
        $admin = $this->createUserForOrganization($a, User::ROLE_ADMIN);
        $job = $this->createJobForOrganization($a);

        // Validation now runs: a bad email and a mismatched confirmation are errors.
        $this->open($admin, $a->id)
            ->set('form.name', 'New Driver')
            ->set('form.email', 'not-an-email')
            ->set('form.password', 'secret1')
            ->set('form.password_confirmation', 'different')
            ->call('createUser')
            ->assertHasErrors(['form.email', 'form.password_confirmation']);

        $this->open($admin, $a->id)
            ->set('form.name', 'New Driver')
            ->set('form.email', 'driver@example.test')
            ->set('form.password', 'secret1')
            ->set('form.password_confirmation', 'secret1')
            ->set('form.role', User::ROLE_EMPLOYEE_STANDARD)
            ->call('createUser')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'driver@example.test',
            'organization_id' => $a->id,
            'organization_role' => User::ROLE_EMPLOYEE_STANDARD,
        ]);

        $this->open($admin, $a->id)->call('deleteJobs')->assertOk();
        $this->assertNull(PilotCarJob::withTrashed()->find($job->id));
    }

    public function test_manager_can_reach_the_importer_but_not_the_resets(): void
    {
        $a = $this->createOrganization('A');
        $manager = $this->createUserForOrganization($a, User::ROLE_EMPLOYEE_MANAGER);

        // Authorized: fails on the missing file, not on permission.
        $this->open($manager, $a->id)->call('uploadFile')->assertOk()->assertHasErrors(['file']);

        $this->open($manager, $a->id)->call('deleteJobs')->assertForbidden();
        $this->open($manager, $a->id)->call('deleteUsers')->assertForbidden();
    }
}
