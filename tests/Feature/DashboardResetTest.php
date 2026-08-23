<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobInvoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two reset paths in the dashboard danger zone (TASK-392).
 *
 * There used to be one button, and every statement behind it was
 * where('id', '!=', 0) -- every row in the table. Clearing your own test data
 * took every other organization's with it. Both blast radii are wanted; they
 * are now separate, and each says which one it is.
 */
class DashboardResetTest extends TestCase
{
    use RefreshDatabase;

    private Organization $mine;
    private Organization $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->mine = Organization::factory()->create(['name' => 'Casco Bay']);
        $this->theirs = Organization::factory()->create(['name' => 'Someone Else']);
    }

    /**
     * A job with a log and an invoice, plus the pivot row a summary would carry.
     */
    private function seedOrganization(Organization $organization, string $jobNo): array
    {
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);

        $job = PilotCarJob::create([
            'job_no' => $jobNo,
            'customer_id' => $customer->id,
            'organization_id' => $organization->id,
            'load_no' => 'LOAD-'.$jobNo,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '575.00',
        ]);

        $log = UserLog::create([
            'job_id' => $job->id,
            'organization_id' => $organization->id,
            'vehicle_id' => Vehicle::factory()->create(['organization_id' => $organization->id])->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
        ]);

        $invoice = Invoice::create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $job->id,
            'values' => ['total' => 575],
        ]);

        $pivot = JobInvoice::create([
            'invoice_id' => $invoice->id,
            'pilot_car_job_id' => $job->id,
        ]);

        return compact('job', 'log', 'invoice', 'pivot');
    }

    private function admin(Organization $organization): User
    {
        return User::factory()->admin()->create(['organization_id' => $organization->id]);
    }

    private function superUser(Organization $organization): User
    {
        return User::factory()->create(['organization_id' => $organization->id, 'is_super' => true]);
    }

    private function assertGone(array $seeded): void
    {
        $this->assertNull(PilotCarJob::withTrashed()->find($seeded['job']->id));
        $this->assertNull(Invoice::withTrashed()->find($seeded['invoice']->id));
        $this->assertNull(UserLog::find($seeded['log']->id));
        $this->assertNull(JobInvoice::find($seeded['pivot']->id));
    }

    private function assertPresent(array $seeded): void
    {
        $this->assertNotNull(PilotCarJob::withTrashed()->find($seeded['job']->id));
        $this->assertNotNull(Invoice::withTrashed()->find($seeded['invoice']->id));
        $this->assertNotNull(UserLog::find($seeded['log']->id));
        $this->assertNotNull(JobInvoice::find($seeded['pivot']->id));
    }

    // ------------------------------------------------------- organization reset

    /**
     * The whole point of the split: my reset stops at my own organization.
     */
    public function test_an_organization_reset_leaves_other_organizations_alone(): void
    {
        $mine = $this->seedOrganization($this->mine, 'JOB-MINE');
        $theirs = $this->seedOrganization($this->theirs, 'JOB-THEIRS');

        Livewire::actingAs($this->superUser($this->mine))
            ->test(Dashboard::class)
            ->call('resetOrganization');

        $this->assertGone($mine);
        $this->assertPresent($theirs);
    }

    public function test_an_admin_can_reset_their_own_organization(): void
    {
        $mine = $this->seedOrganization($this->mine, 'JOB-A');

        Livewire::actingAs($this->admin($this->mine))
            ->test(Dashboard::class)
            ->call('resetOrganization');

        $this->assertGone($mine);
    }

    /**
     * Archived rows are still rows. A reset that leaves them behind has not
     * reset anything.
     */
    public function test_archived_jobs_and_invoices_go_too(): void
    {
        $mine = $this->seedOrganization($this->mine, 'JOB-ARCHIVED');
        $mine['job']->delete();
        $mine['invoice']->delete();

        Livewire::actingAs($this->admin($this->mine))
            ->test(Dashboard::class)
            ->call('resetOrganization');

        $this->assertGone($mine);
    }

    public function test_a_manager_cannot_reset_an_organization(): void
    {
        $mine = $this->seedOrganization($this->mine, 'JOB-M');

        $manager = User::factory()->manager()->create(['organization_id' => $this->mine->id]);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->call('resetOrganization')
            ->assertForbidden();

        $this->assertPresent($mine);
    }

    // -------------------------------------------------------------- nuclear

    public function test_a_nuclear_reset_clears_every_organization(): void
    {
        $mine = $this->seedOrganization($this->mine, 'JOB-MINE');
        $theirs = $this->seedOrganization($this->theirs, 'JOB-THEIRS');

        Livewire::actingAs($this->superUser($this->mine))
            ->test(Dashboard::class)
            ->call('nuclearReset');

        $this->assertGone($mine);
        $this->assertGone($theirs);
    }

    /**
     * The section is wrapped in an isSuper() check in the blade, but a Livewire
     * action is its own callable endpoint and never sees that markup. An admin
     * may reset their own organization and must not be able to reach this.
     */
    public function test_an_admin_cannot_go_nuclear(): void
    {
        $mine = $this->seedOrganization($this->mine, 'JOB-MINE');
        $theirs = $this->seedOrganization($this->theirs, 'JOB-THEIRS');

        Livewire::actingAs($this->admin($this->mine))
            ->test(Dashboard::class)
            ->call('nuclearReset')
            ->assertForbidden();

        $this->assertPresent($mine);
        $this->assertPresent($theirs);
    }

    public function test_a_manager_cannot_go_nuclear(): void
    {
        $theirs = $this->seedOrganization($this->theirs, 'JOB-THEIRS');

        $manager = User::factory()->manager()->create(['organization_id' => $this->mine->id]);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->call('nuclearReset')
            ->assertForbidden();

        $this->assertPresent($theirs);
    }
}
