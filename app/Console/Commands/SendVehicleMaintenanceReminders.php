<?php

namespace App\Console\Commands;

use App\Listeners\Concerns\SendsNotificationMail;
use App\Mail\UserNotification;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendVehicleMaintenanceReminders extends Command
{
    use SendsNotificationMail;

    /**
     * How long to wait before re-reminding about the same vehicle while its
     * due/overdue condition persists. Matches the "due soon" window on the
     * Vehicle status helpers.
     */
    public const REMIND_EVERY_DAYS = 7;

    protected $signature = 'vehicles:send-maintenance-reminders
                            {--dry-run : List what would be sent without sending or stamping anything}';

    protected $description = 'Email org admins/managers a digest of vehicles with oil changes or inspections due soon / overdue (TASK-041)';

    public function handle(): int
    {
        // Candidate set: in-service vehicles with at least one due date that the
        // Vehicle status helpers could flag. Overdue = date passed; due soon =
        // within 7 days. Vehicles reminded in the last REMIND_EVERY_DAYS are
        // skipped so a persistent condition nags weekly, not daily.
        $horizon = now()->addDays(self::REMIND_EVERY_DAYS)->toDateString();

        $vehicles = Vehicle::query()
            ->where('is_in_service', true)
            ->where(function ($query) use ($horizon) {
                $query->whereDate('next_oil_change_due_at', '<=', $horizon)
                    ->orWhereDate('next_inspection_due_at', '<=', $horizon);
            })
            ->where(function ($query) {
                $query->whereNull('maintenance_reminder_sent_at')
                    ->orWhere('maintenance_reminder_sent_at', '<=', now()->subDays(self::REMIND_EVERY_DAYS));
            })
            ->with('organization')
            ->get()
            // The date filter above is a coarse cut; the status helpers are the
            // source of truth (they also power the vehicle page badges).
            ->filter(fn (Vehicle $vehicle) => $this->dueItems($vehicle) !== []);

        if ($vehicles->isEmpty()) {
            $this->info('No vehicles need a maintenance reminder.');

            return self::SUCCESS;
        }

        foreach ($vehicles->groupBy('organization_id') as $organizationId => $orgVehicles) {
            $organization = $orgVehicles->first()->organization;
            $orgName = $organization?->name ?: 'your organization';

            $recipients = User::query()
                ->where('organization_id', $organizationId)
                ->whereIn('organization_role', [User::ROLE_ADMIN, User::ROLE_EMPLOYEE_MANAGER])
                ->get()
                ->map(fn (User $user) => trim($user->notification_address ?: $user->email ?: ''))
                ->filter()
                ->unique()
                ->values();

            $lines = $orgVehicles
                ->flatMap(fn (Vehicle $vehicle) => $this->dueItems($vehicle))
                ->values();

            $this->line(sprintf('%s: %d item(s) -> %d recipient(s)', $orgName, $lines->count(), $recipients->count()));
            $lines->each(fn (array $item) => $this->line('  - '.$item['line']));

            if ($this->option('dry-run')) {
                continue;
            }

            if ($recipients->isEmpty()) {
                // No one to tell — leave the stamp unset so the digest fires as
                // soon as the org gains an admin/manager address.
                Log::warning('Maintenance reminder skipped: organization has no admin/manager recipients', [
                    'organization_id' => $organizationId,
                ]);

                continue;
            }

            $subject = sprintf('Vehicle Maintenance Due: %d item(s)', $lines->count());
            $message = sprintf(
                "Vehicle maintenance needs attention.\n%s\nManage vehicles: %s",
                $lines->pluck('line')->implode("\n"),
                route('my.vehicles.index')
            );

            foreach ($recipients as $address) {
                $this->mailSafely($address, new UserNotification($message, $subject, 'mail.maintenance-due', [
                    'items' => $lines->all(),
                    'orgName' => $orgName,
                ], $orgName));
            }

            // Stamp only after a delivery attempt so an org that couldn't be
            // notified isn't silently marked as reminded.
            Vehicle::whereIn('id', $orgVehicles->pluck('id'))
                ->update(['maintenance_reminder_sent_at' => now()]);
        }

        return self::SUCCESS;
    }

    /**
     * The due/overdue items for one vehicle, each with a compact plain-text
     * line (SMS-gateway friendly) plus structured fields for the HTML digest.
     *
     * @return array<int, array{vehicle: string, item: string, status: string, due: string, line: string}>
     */
    private function dueItems(Vehicle $vehicle): array
    {
        $items = [];

        foreach ([
            ['label' => 'Oil change', 'status' => $vehicle->getOilChangeStatus(), 'due' => $vehicle->next_oil_change_due_at],
            ['label' => 'Inspection', 'status' => $vehicle->getInspectionStatus(), 'due' => $vehicle->next_inspection_due_at],
        ] as $check) {
            if (! in_array($check['status'], ['overdue', 'due_soon'], true)) {
                continue;
            }

            $items[] = [
                'vehicle' => $vehicle->name,
                'item' => $check['label'],
                'status' => $check['status'],
                'due' => $check['due']->toFormattedDateString(),
                'line' => sprintf(
                    '%s: %s %s %s',
                    $vehicle->name,
                    $check['label'],
                    $check['status'] === 'overdue' ? 'overdue since' : 'due',
                    $check['due']->format('M j')
                ),
            ];
        }

        return $items;
    }
}
