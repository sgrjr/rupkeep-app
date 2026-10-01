<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TASK-474. Route::resource registered actions with no handler (a 500), a
 * missing view (a 500) or a dd(); the users and vehicles resources had empty
 * show/edit/update bodies; MyJobsController pointed at views that do not
 * exist; the cross-organization jobs list looked a customer up unscoped; and
 * Jetstream Teams code, views and tests sat in the tree for a feature that
 * was never on.
 */
class DeadRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_without_a_working_handler_are_gone(): void
    {
        foreach ([
            'customers.show',
            'customers.contacts.index',
            'customers.contacts.create',
            'customers.contacts.show',
            'customers.contacts.edit',
            'my.users.show',
            'my.users.edit',
            'my.users.update',
            'my.vehicles.show',
        ] as $name) {
            $this->assertFalse(Route::has($name), "{$name} should no longer be registered");
        }

        foreach ([
            'customers.index', 'customers.store', 'customers.update', 'customers.destroy',
            'customers.contacts.store', 'customers.contacts.update', 'customers.contacts.destroy',
            'my.users.index', 'my.users.create', 'my.users.store', 'my.users.destroy',
            'my.vehicles.index', 'my.vehicles.edit', 'my.vehicles.update', 'my.vehicles.destroy',
            'my.jobs.create', 'my.jobs.edit', 'my.jobs.show',
        ] as $name) {
            $this->assertTrue(Route::has($name), "{$name} must still exist");
        }
    }

    public function test_the_old_show_urls_no_longer_reach_a_handler_that_500s(): void
    {
        $organization = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $organization->id]);

        // The paths still exist for PUT/DELETE, so a GET is a 405 (or a 404),
        // never the 500 the empty show() methods produced.
        foreach (['/my/vehicles/1', '/my/users/'.$manager->id, '/customers/1'] as $url) {
            $status = $this->actingAs($manager)->get($url)->getStatusCode();

            $this->assertContains($status, [404, 405], "{$url} returned {$status}");
        }
    }

    public function test_the_cross_organization_jobs_list_does_not_name_another_organizations_customer(): void
    {
        $mine = Organization::factory()->create();
        $theirs = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $mine->id]);
        $foreignCustomer = Customer::factory()->create(['organization_id' => $theirs->id, 'name' => 'Rival Freight Lines']);
        PilotCarJob::factory()->create(['organization_id' => $theirs->id, 'customer_id' => $foreignCustomer->id]);

        $response = $this->actingAs($admin)->get(route('jobs.index', ['customer' => $foreignCustomer->id]));

        if ($response->getStatusCode() === 200) {
            $response->assertDontSee('Rival Freight Lines');
        } else {
            $this->assertContains($response->getStatusCode(), [302, 403]);
        }
    }

    public function test_the_teams_remnants_are_gone(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/Team.php'));
        $this->assertFileDoesNotExist(app_path('Policies/TeamPolicy.php'));
        $this->assertFileDoesNotExist(app_path('Actions/Jetstream/CreateTeam.php'));
        $this->assertDirectoryDoesNotExist(resource_path('views/teams'));
        $this->assertFileDoesNotExist(resource_path('views/livewire/show-pilot-car-job-0.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/components/navigation-menu.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/auth/register.blade.php'));
        $this->assertStringNotContainsString('createTeam', file_get_contents(app_path('Actions/Fortify/CreateNewUser.php')));
    }
}
