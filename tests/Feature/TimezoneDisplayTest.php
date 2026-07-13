<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TimezoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_index_renders_pickup_time_in_eastern(): void
    {
        Config::set('app.display_timezone', 'America/New_York');

        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);

        // 16:00 UTC in summer = 12:00 PM EDT (not 4:00 PM).
        PilotCarJob::factory()->create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'job_no' => 'TZ-JOB',
            'scheduled_pickup_at' => '2026-07-13 16:00:00',
        ]);

        $response = $this->actingAs($manager)->get(route('my.jobs.index'));

        $response->assertOk()
            ->assertSee('12:00 PM')       // converted to Eastern
            ->assertDontSee('4:00 PM');   // the raw UTC time must not leak through
    }
}
