<?php

namespace Tests\Feature;

use App\Livewire\EditUserLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class LogTripTimingSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function editComponent(array $logAttributes)
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create(['organization_id' => $org->id]);
        $customer = Customer::factory()->create(['organization_id' => $org->id]);
        $job = PilotCarJob::factory()->create([
            'organization_id' => $org->id, 'customer_id' => $customer->id,
        ]);

        $log = UserLog::create(array_merge([
            'job_id' => $job->id,
            'organization_id' => $org->id,
        ], $logAttributes));

        return Livewire::actingAs($manager)->test(EditUserLog::class, ['log' => $log]);
    }

    public function test_trip_timing_summary_shows_clock_only_times(): void
    {
        // Clock in/out set, job start/end null — previously produced no summary.
        $this->editComponent([
            'clock_in' => Carbon::parse('2026-07-12 08:00'),
            'clock_out' => Carbon::parse('2026-07-12 17:30'),
        ])->assertSeeText('Clock:');
    }

    public function test_trip_timing_summary_shows_job_times(): void
    {
        $this->editComponent([
            'started_at' => Carbon::parse('2026-07-12 09:15'),
            'ended_at' => Carbon::parse('2026-07-12 15:45'),
        ])->assertSeeText('Job:');
    }
}
