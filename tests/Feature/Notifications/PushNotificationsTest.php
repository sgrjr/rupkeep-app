<?php

namespace Tests\Feature\Notifications;

use App\Events\JobWasCanceled;
use App\Events\JobWasUncanceled;
use App\Listeners\NotifyAssignedDriversOfJobCancellation;
use App\Listeners\NotifyAssignedDriversOfJobUncancellation;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\UserLog;
use App\Notifications\JobUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * TASK-467. The service worker read the link from the wrong key and opened
 * `undefined`; an existing browser subscription was never re-sent to the
 * server; there was no way to unsubscribe on logout; the VAPID key was typed
 * into the view; and the cancellation notice, the most urgent message there
 * is, never reached the phone's notification tray.
 */
class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://push.example.test/send/abc123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function subscriptionPayload(string $endpoint = self::ENDPOINT): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['auth' => 'auth-token', 'p256dh' => 'p256dh-key']];
    }

    public function test_the_vapid_key_is_rendered_from_config_not_the_source(): void
    {
        config(['webpush.vapid.public_key' => 'TEST-PUBLIC-KEY-FROM-ENV']);
        $user = User::factory()->manager()->create(['organization_id' => Organization::factory()->create()->id]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('TEST-PUBLIC-KEY-FROM-ENV', $html);
        $this->assertStringNotContainsString('BMPgW_eNDtZPVH', $html, 'the old hard-coded key is gone');
        $this->assertStringContainsString(url('/notifications/unsubscribe'), $html);
    }

    public function test_without_a_vapid_key_the_page_does_not_try_to_subscribe(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $user = User::factory()->manager()->create(['organization_id' => Organization::factory()->create()->id]);

        $html = $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('pushManager', $html);
    }

    public function test_subscribing_is_an_upsert_and_moves_a_shared_device_to_the_current_user(): void
    {
        $organization = Organization::factory()->create();
        $first = User::factory()->standard()->create(['organization_id' => $organization->id]);
        $second = User::factory()->standard()->create(['organization_id' => $organization->id]);

        $this->actingAs($first)->postJson(route('notifications.subscribe'), $this->subscriptionPayload())->assertOk();
        $this->actingAs($first)->postJson(route('notifications.subscribe'), $this->subscriptionPayload())->assertOk();

        $this->assertSame(1, $first->pushSubscriptions()->count(), 'sending the same subscription twice is one row');

        // Someone else signs in on the same phone.
        $this->actingAs($second)->postJson(route('notifications.subscribe'), $this->subscriptionPayload())->assertOk();

        $this->assertSame(0, $first->pushSubscriptions()->count());
        $this->assertSame(1, $second->pushSubscriptions()->count());
    }

    public function test_logging_out_forgets_this_devices_subscription_only(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->standard()->create(['organization_id' => $organization->id]);
        $user->updatePushSubscription(self::ENDPOINT, 'k', 't');
        $user->updatePushSubscription('https://push.example.test/send/desktop', 'k', 't');

        $this->actingAs($user)->postJson(route('notifications.unsubscribe'), ['endpoint' => self::ENDPOINT])->assertOk();

        $this->assertSame(1, $user->pushSubscriptions()->count());
        $this->assertSame('https://push.example.test/send/desktop', $user->pushSubscriptions()->value('endpoint'));
    }

    public function test_a_guest_cannot_touch_subscriptions(): void
    {
        $this->postJson(route('notifications.unsubscribe'), ['endpoint' => self::ENDPOINT])->assertUnauthorized();
        $this->postJson(route('notifications.subscribe'), $this->subscriptionPayload())->assertUnauthorized();
    }

    public function test_the_service_worker_reads_the_link_where_the_notification_puts_it(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString('payload.data && payload.data.url', $worker);
        $this->assertStringContainsString("|| '/'", $worker, 'a missing link falls back to the site root');
        $this->assertStringContainsString('try {', $worker, 'a non-JSON payload does not throw');
        $this->assertStringNotContainsString('console.log', $worker);
        $this->assertStringNotContainsString('/assets/favicon.ico', $worker);
    }

    public function test_the_push_icon_exists(): void
    {
        $job = PilotCarJob::factory()->create();
        $message = (new JobUpdate($job, 'Title', 'Body'))->toWebPush($job, null);

        $payload = $message->toArray();

        $this->assertSame('/favicon.ico', $payload['icon']);
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertSame(route('my.jobs.show', ['job' => $job->id]), $payload['data']['url']);
    }

    private function jobWithDriver(): array
    {
        $organization = Organization::factory()->create();
        $customer = Customer::factory()->create(['organization_id' => $organization->id]);
        $driver = User::factory()->standard()->create(['organization_id' => $organization->id, 'notification_address' => null]);
        $job = PilotCarJob::factory()->create(['organization_id' => $organization->id, 'customer_id' => $customer->id]);

        UserLog::create([
            'job_id' => $job->id,
            'car_driver_id' => $driver->id,
            'organization_id' => $organization->id,
            'vehicle_position' => 'lead',
            'approval_status' => 'confirmed',
        ]);

        return [$job, $driver];
    }

    public function test_a_cancellation_reaches_the_phone_tray(): void
    {
        [$job, $driver] = $this->jobWithDriver();

        Notification::fake();
        Bus::fake();
        Mail::fake();

        (new NotifyAssignedDriversOfJobCancellation())->handle(new JobWasCanceled($job, 'Load not ready', 'cancel_without_billing'));

        Notification::assertSentTo($driver, JobUpdate::class, function (JobUpdate $n) use ($job, $driver) {
            $payload = $n->toWebPush($driver, $n)->toArray();

            return str_contains($payload['title'], 'Canceled')
                && str_contains($payload['body'], 'Do not proceed')
                && str_contains($payload['body'], 'Load not ready')
                && $payload['data']['url'] === route('my.jobs.show', ['job' => $job->id]);
        });
    }

    public function test_a_reactivation_reaches_the_phone_tray(): void
    {
        [$job, $driver] = $this->jobWithDriver();

        Notification::fake();
        Bus::fake();
        Mail::fake();

        (new NotifyAssignedDriversOfJobUncancellation())->handle(new JobWasUncanceled($job, 'Load not ready'));

        Notification::assertSentTo($driver, JobUpdate::class, fn (JobUpdate $n) => str_contains($n->toWebPush($driver, $n)->toArray()['title'], 'Reactivated'));
    }
}
