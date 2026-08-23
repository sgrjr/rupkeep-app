<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-390 — the jobs list turned `search_field` straight into a method call:
 *
 *     $scope = Str::camel($request->search_field);
 *     $jobs = (clone $query)->$scope()->...
 *
 * with no whitelist on the else branch, in both MyJobsController and
 * JobsController. Any method on the Eloquent builder was reachable from the
 * query string, so ?search_field=delete called delete() on the built query --
 * a mass delete from a GET, no confirmation, no POST, no CSRF token.
 *
 * Only the seven scopes on HasJobScopes are a legitimate value here.
 */
class JobSearchFieldTest extends TestCase
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
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function job(string $jobNo): PilotCarJob
    {
        return PilotCarJob::create([
            'job_no' => $jobNo,
            'customer_id' => $this->customer->id,
            'organization_id' => $this->organization->id,
            'load_no' => 'LOAD-'.$jobNo,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);
    }

    /**
     * The one that matters. A GET must never be able to empty the table.
     */
    public function test_a_search_field_of_delete_does_not_delete_the_jobs(): void
    {
        $this->job('JOB-1');
        $this->job('JOB-2');

        $this->actingAs($this->admin)
            ->get(route('my.jobs.index', ['search_field' => 'delete', 'search_value' => '']));

        $this->assertSame(2, PilotCarJob::count(), 'A query-string value must not be able to delete rows.');
    }

    public function test_a_search_field_of_force_delete_does_not_delete_the_jobs(): void
    {
        $this->job('JOB-1');

        $this->actingAs($this->admin)
            ->get(route('my.jobs.index', ['search_field' => 'force_delete', 'search_value' => '']));

        $this->assertSame(1, PilotCarJob::withTrashed()->count());
    }

    /**
     * An unknown value should simply not filter, rather than reaching for a
     * method of that name and blowing up (or worse).
     */
    public function test_an_unknown_search_field_is_ignored_not_invoked(): void
    {
        $this->job('JOB-1');

        $this->actingAs($this->admin)
            ->get(route('my.jobs.index', ['search_field' => 'not_a_real_scope', 'search_value' => '']))
            ->assertOk();

        $this->assertSame(1, PilotCarJob::count());
    }

    /**
     * The legitimate values still work — the whitelist must not break the
     * feature it is protecting.
     */
    public function test_the_real_scopes_still_filter(): void
    {
        $paid = $this->job('JOB-PAID');
        $paid->update(['invoice_paid' => 1]);

        $unpaid = $this->job('JOB-UNPAID');

        $response = $this->actingAs($this->admin)
            ->get(route('my.jobs.index', ['search_field' => 'is_paid', 'search_value' => '']))
            ->assertOk();

        $response->assertSee('JOB-PAID');
        $response->assertDontSee('JOB-UNPAID');
    }

    public function test_the_cross_organization_list_is_protected_too(): void
    {
        $this->job('JOB-1');
        $this->job('JOB-2');

        $super = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_super' => true,
        ]);

        $this->actingAs($super)
            ->get(route('jobs.index', ['search_field' => 'delete', 'search_value' => '']));

        $this->assertSame(2, PilotCarJob::count());
    }
}
