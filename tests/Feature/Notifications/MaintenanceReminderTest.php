<?php

namespace Tests\Feature\Notifications;

use App\Mail\UserNotification;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MaintenanceReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_digest_sent_to_managers_for_due_vehicles_only(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create(['name' => 'Casco Bay Pilot Car']);

        $manager = User::factory()->manager()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'manager@example.com',
        ]);

        $overdueOil = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Car 001',
            'next_oil_change_due_at' => now()->subDays(3)->toDateString(),
        ]);

        $dueSoonInspection = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Car 002',
            'next_inspection_due_at' => now()->addDays(5)->toDateString(),
        ]);

        $farFuture = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Car 003',
            'next_oil_change_due_at' => now()->addMonths(3)->toDateString(),
        ]);

        $noDates = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'Car 004',
        ]);

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) use ($organization) {
            $vehicles = collect($mail->viewData['items'])->pluck('vehicle');

            return $mail->hasTo('manager@example.com')
                && str_contains($mail->subject, 'Vehicle Maintenance Due')
                && $mail->fromName === $organization->name
                && $vehicles->contains('Car 001')
                && $vehicles->contains('Car 002')
                && ! $vehicles->contains('Car 003')
                && ! $vehicles->contains('Car 004');
        });

        // Only the vehicles that made the digest get the re-remind stamp.
        $this->assertNotNull($overdueOil->fresh()->maintenance_reminder_sent_at);
        $this->assertNotNull($dueSoonInspection->fresh()->maintenance_reminder_sent_at);
        $this->assertNull($farFuture->fresh()->maintenance_reminder_sent_at);
        $this->assertNull($noDates->fresh()->maintenance_reminder_sent_at);
    }

    public function test_reminder_not_repeated_within_a_week(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();

        User::factory()->manager()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'manager@example.com',
        ]);

        Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'next_oil_change_due_at' => now()->subDay()->toDateString(),
        ]);

        // Count only the manager's mail: Organization::factory() also creates
        // an admin owner who legitimately receives the digest too.
        $managerMails = fn () => Mail::sent(UserNotification::class)
            ->filter(fn (UserNotification $mail) => $mail->hasTo('manager@example.com'))
            ->count();

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();
        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        $this->assertSame(1, $managerMails());

        // After the re-remind window the still-overdue vehicle nags again.
        $this->travel(8)->days();
        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        $this->assertSame(2, $managerMails());
    }

    public function test_out_of_service_vehicles_are_skipped(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();

        User::factory()->manager()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'manager@example.com',
        ]);

        Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'is_in_service' => false,
            'next_oil_change_due_at' => now()->subDays(30)->toDateString(),
        ]);

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_digests_are_scoped_per_organization(): void
    {
        Mail::fake();

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        User::factory()->manager()->create([
            'organization_id' => $orgA->id,
            'notification_address' => 'manager-a@example.com',
        ]);
        User::factory()->manager()->create([
            'organization_id' => $orgB->id,
            'notification_address' => 'manager-b@example.com',
        ]);

        Vehicle::factory()->create([
            'organization_id' => $orgA->id,
            'name' => 'Org A Car',
            'next_oil_change_due_at' => now()->subDay()->toDateString(),
        ]);
        Vehicle::factory()->create([
            'organization_id' => $orgB->id,
            'name' => 'Org B Car',
            'next_inspection_due_at' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            if (! $mail->hasTo('manager-a@example.com')) {
                return false;
            }
            $vehicles = collect($mail->viewData['items'])->pluck('vehicle');

            return $vehicles->contains('Org A Car') && ! $vehicles->contains('Org B Car');
        });

        Mail::assertSent(UserNotification::class, function (UserNotification $mail) {
            if (! $mail->hasTo('manager-b@example.com')) {
                return false;
            }
            $vehicles = collect($mail->viewData['items'])->pluck('vehicle');

            return $vehicles->contains('Org B Car') && ! $vehicles->contains('Org A Car');
        });
    }

    public function test_org_without_recipients_is_not_stamped(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();

        // Organization::factory() creates an admin owner — remove them so the
        // org genuinely has no admin/manager, only a standard driver.
        User::query()->where('organization_id', $organization->id)->delete();
        User::factory()->standard()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'driver@example.com',
        ]);

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'next_oil_change_due_at' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('vehicles:send-maintenance-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        // Stamp stays unset so the digest fires once the org gains a manager.
        $this->assertNull($vehicle->fresh()->maintenance_reminder_sent_at);
    }

    public function test_dry_run_sends_and_stamps_nothing(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();

        User::factory()->manager()->create([
            'organization_id' => $organization->id,
            'notification_address' => 'manager@example.com',
        ]);

        $vehicle = Vehicle::factory()->create([
            'organization_id' => $organization->id,
            'next_oil_change_due_at' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('vehicles:send-maintenance-reminders', ['--dry-run' => true])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($vehicle->fresh()->maintenance_reminder_sent_at);
    }
}
