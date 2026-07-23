<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-034 — after login, managers land on the Jobs index; every other role
 * keeps the default destination (/dashboard). Directly visiting /dashboard
 * still works for managers — only the default landing changes.
 */
class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_login_lands_on_jobs_index(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->forOrganization($org)->create();

        $this->post('/login', [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertRedirect(route('my.jobs.index'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_driver_login_lands_on_dashboard(): void
    {
        $org = Organization::factory()->create();
        $driver = User::factory()->standard()->forOrganization($org)->create();

        $this->post('/login', [
            'email' => $driver->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($driver);
    }

    public function test_admin_login_lands_on_dashboard(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->forOrganization($org)->create();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_customer_login_lands_on_dashboard(): void
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $customerUser = User::factory()->asCustomer($customer)->create();

        $this->post('/login', [
            'email' => $customerUser->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($customerUser);
    }

    public function test_manager_can_still_visit_dashboard_directly(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->forOrganization($org)->create();

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk();
    }
}
