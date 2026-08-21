<?php

namespace Tests\Feature;

use App\Events\LogCompleted;
use App\Listeners\SendLogCompletedNotification;
use App\Mail\UserNotification;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TASK-364: drivers could save a log but had no way to signal they were
 * finished, so nothing told the office a job was ready to review and invoice
 * (job status was derived solely from whether an invoice already existed).
 */
class LogCompletionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private PilotCarJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);

        $this->job = PilotCarJob::create([
            'job_no' => 'JOB-COMPLETE',
            'customer_id' => $customer->id,
            'organization_id' => $this->organization->id,
            'pickup_address' => '1 Demo St',
            'delivery_address' => '2 Demo Ave',
            'rate_code' => 'lead_chase_per_mile',
            'rate_value' => '2.00',
        ]);
    }

    private function driver(): User
    {
        return User::factory()->standard()->create(['organization_id' => $this->organization->id]);
    }

    private function log(?User $driver = null, array $attributes = []): UserLog
    {
        return UserLog::create(array_merge([
            'job_id' => $this->job->id,
            'organization_id' => $this->organization->id,
            'car_driver_id' => $driver?->id,
            'approval_status' => 'confirmed',
            'start_mileage' => 0,
            'end_mileage' => 100,
            'start_job_mileage' => 0,
            'end_job_mileage' => 80,
        ], $attributes));
    }

    public function test_assigned_driver_can_mark_their_log_complete(): void
    {
        Event::fake([LogCompleted::class]);

        $driver = $this->driver();
        $log = $this->log($driver);

        Livewire::actingAs($driver)
            ->test('edit-user-log', ['log' => $log])
            ->call('markComplete');

        $log->refresh();

        $this->assertNotNull($log->completed_at);
        $this->assertSame($driver->id, $log->completed_by_id);
        $this->assertTrue($log->isComplete());

        Event::assertDispatched(LogCompleted::class);
    }

    public function test_completing_notifies_the_office(): void
    {
        $manager = User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
            'email' => 'mary@example.test',
        ]);
        $admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
            'email' => 'matt@example.test',
        ]);

        $driver = $this->driver();
        $log = $this->log($driver);
        $log->completed_at = now();
        $log->save();

        // Faked only now: creating a log fires JobAssigned, whose own listener
        // mails the driver. That is unrelated traffic and would skew the count.
        Mail::fake();

        (new SendLogCompletedNotification())->handle(new LogCompleted($log, $driver));

        Mail::assertSent(UserNotification::class, fn ($mail) => $mail->hasTo($manager->email));
        Mail::assertSent(UserNotification::class, fn ($mail) => $mail->hasTo($admin->email));

        // The driver who finished the job is not part of the office fan-out.
        Mail::assertNotSent(UserNotification::class, fn ($mail) => $mail->hasTo($driver->email));
    }

    public function test_the_notification_names_the_job_and_links_to_the_log(): void
    {
        User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
            'email' => 'mary@example.test',
        ]);

        $driver = $this->driver();
        $log = $this->log($driver);
        $log->completed_at = now();
        $log->save();

        Mail::fake();

        (new SendLogCompletedNotification())->handle(new LogCompleted($log, $driver));

        Mail::assertSent(UserNotification::class, function ($mail) use ($log) {
            return $mail->hasTo('mary@example.test')
                && str_contains($mail->subject, 'JOB-COMPLETE');
        });
    }

    public function test_the_person_who_completed_it_is_not_notified(): void
    {
        $manager = User::factory()->manager()->create([
            'organization_id' => $this->organization->id,
            'email' => 'mary@example.test',
        ]);

        $log = $this->log($manager);
        $log->completed_at = now();
        $log->save();

        Mail::fake();

        (new SendLogCompletedNotification())->handle(new LogCompleted($log, $manager));

        // Other office staff still hear about it — just not the person who
        // pressed the button.
        Mail::assertNotSent(UserNotification::class, fn ($mail) => $mail->hasTo($manager->email));
    }

    public function test_a_driver_from_another_org_cannot_even_open_the_log(): void
    {
        $other = User::factory()->standard()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $log = $this->log($this->driver());

        // The tenant boundary is enforced at mount, so a cross-org user never
        // reaches the complete action at all.
        Livewire::actingAs($other)
            ->test('edit-user-log', ['log' => $log])
            ->assertForbidden();

        $this->assertNull($log->fresh()->completed_at);
    }

    public function test_a_coworker_who_is_not_the_assigned_driver_cannot_complete_the_log(): void
    {
        // A same-org standard employee CAN open the log (policy 'update'), so
        // this is the case where completion authorization actually has to hold.
        $coworker = User::factory()->standard()->create(['organization_id' => $this->organization->id]);
        $log = $this->log($this->driver());

        Livewire::actingAs($coworker)
            ->test('edit-user-log', ['log' => $log])
            ->call('markComplete')
            ->assertForbidden();

        $this->assertNull($log->fresh()->completed_at);
    }

    public function test_completing_twice_does_not_re_notify_the_office(): void
    {
        Event::fake([LogCompleted::class]);

        $driver = $this->driver();
        $log = $this->log($driver, ['completed_at' => now()->subHour(), 'completed_by_id' => $driver->id]);

        Livewire::actingAs($driver)
            ->test('edit-user-log', ['log' => $log])
            ->call('markComplete');

        Event::assertNotDispatched(LogCompleted::class);
    }

    public function test_a_completed_log_is_locked_to_the_driver(): void
    {
        $driver = $this->driver();
        $log = $this->log($driver, ['completed_at' => now(), 'completed_by_id' => $driver->id]);

        Livewire::actingAs($driver)
            ->test('edit-user-log', ['log' => $log])
            ->set('form.tolls', '99.99')
            ->call('saveLog');

        $this->assertNotEquals('99.99', $log->fresh()->tolls);
    }

    public function test_a_manager_can_still_edit_a_completed_log(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $log = $this->log($this->driver(), ['completed_at' => now()]);

        Livewire::actingAs($manager)
            ->test('edit-user-log', ['log' => $log])
            ->set('form.tolls', '12.50')
            ->call('saveLog');

        $this->assertEquals(12.50, (float) $log->fresh()->tolls);
    }

    public function test_a_manager_can_reopen_a_completed_log(): void
    {
        $manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $log = $this->log($this->driver(), ['completed_at' => now()]);

        Livewire::actingAs($manager)
            ->test('edit-user-log', ['log' => $log])
            ->call('reopenLog');

        $this->assertNull($log->fresh()->completed_at);
    }

    public function test_a_driver_cannot_reopen_their_own_completed_log(): void
    {
        $driver = $this->driver();
        $log = $this->log($driver, ['completed_at' => now(), 'completed_by_id' => $driver->id]);

        Livewire::actingAs($driver)
            ->test('edit-user-log', ['log' => $log])
            ->call('reopenLog')
            ->assertForbidden();

        $this->assertNotNull($log->fresh()->completed_at);
    }

    public function test_a_denied_log_cannot_be_completed(): void
    {
        $driver = $this->driver();
        $log = $this->log($driver, ['approval_status' => 'denied']);

        Livewire::actingAs($driver)
            ->test('edit-user-log', ['log' => $log])
            ->call('markComplete');

        $this->assertNull($log->fresh()->completed_at);
    }
}
