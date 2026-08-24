<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for `jobs:audit` (TASK-354).
 *
 * The command's value depends entirely on a clean run meaning something. If it
 * reported findings on healthy data it would be ignored within a week, and if
 * it stayed quiet on a broken invariant it would be worse than nothing. These
 * tests pin both directions.
 */
class JobDataAuditTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private PilotCarJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $this->job = PilotCarJob::factory()->create([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'rate_code' => 'lead_chase_per_mile',
        ]);
    }

    private function log(array $attributes = []): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $this->job->id,
            'organization_id' => $this->org->id,
        ], $attributes));
    }

    public function test_healthy_data_reports_nothing(): void
    {
        $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1100,
            'end_job_mileage' => 1200,
            'end_mileage' => 1300,
            'dead_head_driven' => 100,
            'dead_head_billed' => 25,
        ]);

        $this->artisan('jobs:audit')
            ->expectsOutputToContain('All checks passed')
            ->assertSuccessful();
    }

    /**
     * The published allowance is a hard ceiling. Reaching past it means
     * something wrote around the form guard, which is a defect, not a choice.
     */
    public function test_billing_above_the_ceiling_is_an_error(): void
    {
        $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1100,
            'end_job_mileage' => 1200,
            'end_mileage' => 1300,
            'dead_head_driven' => 100,
            'dead_head_billed' => 90, // ceiling is 25
        ]);

        $this->artisan('jobs:audit')
            ->expectsOutputToContain('above the')
            ->assertFailed();
    }

    /**
     * A charge with no measurement behind it cannot be explained to a customer.
     */
    public function test_billing_with_nothing_driven_is_an_error(): void
    {
        $this->log(['dead_head_billed' => 40]);

        $this->artisan('jobs:audit')
            ->expectsOutputToContain('none are recorded as driven')
            ->assertFailed();
    }

    /**
     * Messy odometer data is a fact about the records, not a broken invariant,
     * so it reports without failing the run.
     */
    public function test_out_of_order_readings_warn_without_failing(): void
    {
        $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 900,
            'end_job_mileage' => 1200,
            'end_mileage' => 1300,
        ]);

        $this->artisan('jobs:audit')
            ->expectsOutputToContain('out of order')
            ->assertSuccessful();
    }

    /**
     * Deadhead is carved out of the miles NOT spent under load, so it can never
     * exceed them.
     */
    public function test_deadhead_exceeding_non_job_miles_warns(): void
    {
        $this->log([
            'start_mileage' => 1000,
            'start_job_mileage' => 1010,
            'end_job_mileage' => 1200,
            'end_mileage' => 1210,
            'dead_head_driven' => 500, // only 20 miles were driven outside the job
        ]);

        $this->artisan('jobs:audit')
            ->expectsOutputToContain('driven outside the job')
            ->assertSuccessful();
    }

    public function test_audit_can_be_scoped_to_one_organization(): void
    {
        $this->log(['dead_head_billed' => 40]); // an error in this org

        $otherOrg = Organization::factory()->create();

        $this->artisan("jobs:audit {$otherOrg->id}")
            ->expectsOutputToContain('All checks passed')
            ->assertSuccessful();
    }
}
