<?php

namespace Tests\Feature;

use App\Actions\SendUserNotification;
use App\Events\JobAssigned;
use App\Listeners\SendJobAssignedNotification;
use App\Livewire\NotificationTimeline;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Notifications\JobUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

/**
 * TASK-498 (drive 003): step 03 asks whether each escort "received the
 * assignment notification", and nothing in the app could say. Every send is
 * now recorded, and admins and managers see it on /my/users.
 */
class NotificationTimelineTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $manager;

    private User $driver;

    private PilotCarJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // The array mailer fires MessageSent without reaching anyone. Pinned
        // explicitly: .env mail settings leak into tests otherwise.
        config(['mail.default' => 'array']);

        $this->organization = Organization::factory()->create(['name' => 'Casco Bay Pilot Car']);
        $this->manager = User::factory()->manager()->create(['organization_id' => $this->organization->id]);
        $this->driver = User::factory()->standard()->create([
            'organization_id' => $this->organization->id,
            'email' => 'christina@example.com',
            'notification_address' => null,
        ]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->job = PilotCarJob::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'job_no' => 'JOB 777',
            'scheduled_pickup_at' => now()->addDay(),
        ]);
    }

    private function assignLog(): UserLog
    {
        return UserLog::withoutEvents(fn () => UserLog::create([
            'job_id' => $this->job->id,
            'car_driver_id' => $this->driver->id,
            'organization_id' => $this->organization->id,
            'approval_status' => 'pending',
        ]));
    }

    public function test_an_assignment_email_is_recorded_against_the_driver_and_the_job(): void
    {
        $log = $this->assignLog();

        app(SendJobAssignedNotification::class)->handle(new JobAssigned($this->job, $this->driver, $log));

        $row = NotificationDelivery::where('channel', NotificationDelivery::CHANNEL_EMAIL)->sole();

        $this->assertSame($this->organization->id, $row->organization_id);
        $this->assertSame($this->driver->id, $row->user_id);
        $this->assertSame('christina@example.com', $row->recipient);
        $this->assertSame('job_assigned', $row->kind);
        $this->assertSame(NotificationDelivery::STATUS_SENT, $row->status);
        $this->assertTrue($row->related->is($this->job));
        $this->assertStringContainsString('JOB 777', (string) $row->subject);
        $this->assertNotNull($row->excerpt);
    }

    public function test_a_sign_in_email_is_recorded_without_its_text(): void
    {
        $customerUser = User::factory()->asCustomer(Customer::factory()->create(['organization_id' => $this->organization->id]))
            ->create(['email' => 'richard@example.com']);

        $this->post(route('login-code.store'), ['email' => 'richard@example.com']);

        $row = NotificationDelivery::where('user_id', $customerUser->id)->sole();

        $this->assertSame('login_link', $row->kind);
        $this->assertNull($row->excerpt, 'a stored sign-in link would let a reader sign in as the customer');
    }

    public function test_a_message_that_could_not_be_sent_is_recorded_as_not_delivered(): void
    {
        SendUserNotification::recordUndeliverable($this->driver, 'Job canceled', 'Job Canceled: JOB 777', new \RuntimeException('Brevo down'));

        $row = NotificationDelivery::sole();

        $this->assertSame(NotificationDelivery::STATUS_NOT_DELIVERED, $row->status);
        $this->assertSame($this->driver->id, $row->user_id);
    }

    public function test_a_push_is_recorded_only_for_someone_with_a_device(): void
    {
        $push = fn () => event(new NotificationSent($this->driver, new JobUpdate($this->job, 'Job Assigned: JOB 777', 'Pickup: Arundel'), WebPushChannel::class));

        // No subscribed device: the channel sends nothing, so nothing is recorded.
        $push();
        $this->assertSame(0, NotificationDelivery::count());

        $this->driver->updatePushSubscription('https://push.example/endpoint-1', 'key', 'token');
        $push();

        $row = NotificationDelivery::sole();

        $this->assertSame(NotificationDelivery::CHANNEL_PUSH, $row->channel);
        $this->assertSame('job_update', $row->kind);
        $this->assertNull($row->recipient);
        $this->assertTrue($row->related->is($this->job));
    }

    public function test_managers_see_their_organizations_sends_and_what_the_driver_did(): void
    {
        $log = $this->assignLog();
        app(SendJobAssignedNotification::class)->handle(new JobAssigned($this->job, $this->driver, $log));

        $foreignOrg = Organization::factory()->create();
        NotificationDelivery::create([
            'organization_id' => $foreignOrg->id, 'channel' => 'email', 'status' => 'sent',
            'recipient' => 'someone@elsewhere.example', 'subject' => 'Not yours',
        ]);

        Livewire::actingAs($this->manager)
            ->test(NotificationTimeline::class)
            ->assertSee($this->driver->name)
            ->assertSee('Job assigned')
            ->assertSee('No answer yet')
            ->assertDontSee('someone@elsewhere.example');

        $log->update(['approval_status' => 'confirmed']);

        Livewire::actingAs($this->manager)
            ->test(NotificationTimeline::class)
            ->assertSee('Confirmed the job')
            ->set('status', NotificationDelivery::STATUS_NOT_DELIVERED)
            ->assertSee('Nothing matches these filters.');
    }

    public function test_drivers_and_customers_cannot_see_the_timeline(): void
    {
        Livewire::actingAs($this->driver)->test(NotificationTimeline::class)->assertForbidden();

        $this->actingAs($this->driver)->get(route('my.users.index'))->assertDontSee('data-test="notification-timeline"', false);
        $this->actingAs($this->manager)->get(route('my.users.index'))->assertOk()->assertSee('data-test="notification-timeline"', false);
    }
}
