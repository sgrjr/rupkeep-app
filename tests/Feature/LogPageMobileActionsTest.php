<?php

namespace Tests\Feature;

use App\Livewire\EditUserLog;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-453. The log page's action bar (save state, errors, "Save and Mark
 * Log Complete") was `hidden sm:flex`, so on a phone a driver had only a
 * floating checkmark that saved and nothing else: no way to hand the log to
 * the office, and a failed save showed nothing.
 */
class LogPageMobileActionsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private PilotCarJob $job;
    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->standard()->create(['organization_id' => $this->organization->id]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-453',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => 'Gorham, ME',
            'delivery_address' => 'Boston, MA',
            'rate_code' => 'flat_rate',
            'rate_value' => '500.00',
        ]);
    }

    private function log(string $approval = 'confirmed'): UserLog
    {
        return UserLog::create([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $this->driver->id,
            'approval_status' => $approval,
        ]);
    }

    /** The class list of the action bar, from the rendered page. */
    private function actionBarClasses(string $html): array
    {
        $this->assertMatchesRegularExpression('/data-test="log-action-bar" class="([^"]*)"/', $html);
        preg_match('/data-test="log-action-bar" class="([^"]*)"/', $html, $m);

        return preg_split('/\s+/', trim($m[1]));
    }

    public function test_the_action_bar_with_mark_complete_is_not_hidden_on_phones(): void
    {
        $html = Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log()])
            ->assertSee('Save and Mark Log Complete')
            ->html();

        $classes = $this->actionBarClasses($html);

        $this->assertNotContains('hidden', $classes, 'the bar must render below the sm breakpoint');
        $this->assertContains('sticky', $classes, 'and stay in reach while the driver scrolls');

        // The floating checkmark that only saved is gone; one control set for every width.
        $this->assertStringNotContainsString('fixed bottom-6 right-6', $html);
    }

    public function test_a_refused_save_is_explained_inside_the_component(): void
    {
        // A driver with a pending assignment cannot edit yet; the refusal used
        // to be flashed into a bar that phones never showed.
        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log('pending')])
            ->call('saveLog')
            ->assertSee('Please confirm or deny this log assignment before editing.');

        Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log('denied')])
            ->call('markComplete')
            ->assertSee('This log has been denied and cannot be completed.');
    }

    public function test_controls_are_finger_sized_and_do_not_trigger_ios_zoom(): void
    {
        $html = Livewire::actingAs($this->driver)
            ->test(EditUserLog::class, ['log' => $this->log()])
            ->html();

        // iOS zooms into any control whose font is under 16px; text-base is 16px.
        foreach (['clock_in', 'start_mileage', 'dead_head_driven', 'dead_head_billed', 'vehicle_id', 'memo'] as $id) {
            $this->assertMatchesRegularExpression(
                '/id="' . $id . '"[^>]*class="[^"]*min-h-\[44px\][^"]*text-base[^"]*"/s',
                $html,
                "#{$id} should be 44px tall with 16px text"
            );
        }

        $this->assertStringNotContainsString('border-slate-200 min-h-[44px]', $html, 'the deadhead inputs match their siblings');
    }
}
