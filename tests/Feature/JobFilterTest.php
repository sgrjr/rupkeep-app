<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_flagged_scope_matches_only_jobs_with_a_marked_invoice(): void
    {
        $org = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $flagged = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
        ]);
        Invoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $flagged->id,
            'marked_for_attention' => true,
        ]);

        $notFlagged = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
        ]);
        Invoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $notFlagged->id,
            'marked_for_attention' => false,
        ]);

        $noInvoice = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
        ]);

        $ids = PilotCarJob::isFlagged()->pluck('id')->all();

        $this->assertContains($flagged->id, $ids);
        $this->assertNotContains($notFlagged->id, $ids);
        $this->assertNotContains($noInvoice->id, $ids);
    }

    public function test_jobs_index_flagged_filter_renders_the_flagged_job(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        $flagged = PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'job_no' => 'JOB-FLAGGED',
        ]);
        Invoice::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'pilot_car_job_id' => $flagged->id,
            'marked_for_attention' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('my.jobs.index', ['filter' => 'is_flagged']))
            ->assertOk()
            ->assertSee('JOB-FLAGGED');
    }
}
