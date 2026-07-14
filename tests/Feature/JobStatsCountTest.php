<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JobStatsCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_index_stats_are_independent_counts(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        // Missing job_no, NOT paid, NOT canceled. The old accumulated-query bug
        // required missing AND paid AND canceled, so this was counted as 0.
        PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => null, 'invoice_paid' => null, 'canceled_at' => null,
        ]);

        // Canceled but NOT paid — old bug counted canceled AND paid only.
        PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => 'JOB-CANCELED', 'invoice_paid' => null,
            'canceled_at' => Carbon::now(),
        ]);

        // A plain paid job with a job number.
        PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => 'JOB-PAID', 'invoice_paid' => 1, 'canceled_at' => null,
        ]);

        $response = $this->actingAs($manager)->get(route('my.jobs.index'));

        $response->assertOk()
            ->assertViewHas('totalJobs', 3)
            ->assertViewHas('missingJobNo', 1)   // was 0 under the accumulation bug
            ->assertViewHas('canceledJobs', 1)   // was 0 under the accumulation bug
            ->assertViewHas('paidJobs', 1);
    }

    public function test_missing_job_no_stat_matches_the_filtered_results(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        // Two jobs missing a job number, neither paid nor canceled.
        PilotCarJob::factory()->count(2)->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => null, 'invoice_paid' => null, 'canceled_at' => null,
        ]);
        PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => 'HAS-NUMBER',
        ]);

        // The header stat the dashboard link lands on must equal the number of
        // rows the missing-job-no filter actually returns.
        $this->actingAs($manager)->get(route('my.jobs.index'))
            ->assertViewHas('missingJobNo', 2);

        $filtered = $this->actingAs($manager)
            ->get(route('my.jobs.index', ['search_field' => 'missing_job_no', 'search_value' => '']));
        $filtered->assertOk();
        $this->assertSame(2, $filtered->viewData('jobs')->total());
    }

    public function test_jobs_index_route_renders_for_employee_with_stats(): void
    {
        // Regression for TASK-336: the /jobs route (JobsController) shares the
        // pilot-car-jobs.index view with /my/jobs, but did not pass the stat
        // vars or a paginator, so a standard employee (driver) hit
        // "Undefined variable $totalJobs" then Collection::hasPages.
        $org = Organization::factory()->create();
        $driver = User::factory()->standard()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => 'JOB-PAID', 'invoice_paid' => 1, 'canceled_at' => null,
        ]);
        PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
            'job_no' => null, 'invoice_paid' => null, 'canceled_at' => null,
        ]);

        // The exact URL shape from the bug report: /jobs?organization=<id>&car_driver_id=<id>
        // (note: `organization`, not `organization_id`, so it falls through to
        // the all-jobs branch — which crashed just the same before the fix).
        $response = $this->actingAs($driver)
            ->get(route('jobs.index', ['organization' => $org->id, 'car_driver_id' => 3]));

        $response->assertOk()
            ->assertViewHas('totalJobs', 2)
            ->assertViewHas('paidJobs', 1)
            ->assertViewHas('unpaidJobs', 1)
            ->assertViewHas('missingJobNo', 1);

        // $jobs must be a paginator so the view's pagination calls work.
        $this->assertTrue(method_exists($response->viewData('jobs'), 'hasPages'));
    }
}
