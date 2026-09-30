<?php

namespace App\Livewire\Concerns;

use App\Models\CustomerContact;

/**
 * Inline "add a truck driver" for the job forms (TASK-362).
 *
 * The Default Assignments truck-driver dropdown can only offer contacts that
 * already belong to the selected customer, so a job created for a brand-new
 * company had no selectable truck driver at all — even though the company
 * field directly above it does let you type a new one. This resolves a typed
 * name against the customer the job is actually being filed under, whether
 * that customer already existed or was created in the same submission.
 *
 * Mirrors the matching behaviour in EditUserLog::saveLog().
 */
trait ResolvesTruckDriverContact
{
    /**
     * The truck-driver dropdown's options for a customer: its contacts, each
     * labelled with a phone number when one is on file, behind a "none" row.
     * Shared by the create and edit forms so the two lists read the same
     * (TASK-458): the edit form used to build its own list without phones and
     * left the dropdown empty when the job had no customer.
     *
     * @return array<int, array{name: string, value: int|null}>
     */
    protected function truckDriverOptionsFor(int|string|null $customerId): array
    {
        $options = [
            ['name' => __('(none selected)'), 'value' => null],
        ];

        if (! $customerId) {
            return $options;
        }

        CustomerContact::where('customer_id', $customerId)
            ->orderBy('name')
            ->get()
            ->each(function (CustomerContact $contact) use (&$options) {
                $label = $contact->phone ? $contact->name . ' (' . $contact->phone . ')' : $contact->name;
                $options[] = ['name' => $label, 'value' => $contact->id];
            });

        return $options;
    }

    /**
     * Whether a chosen contact belongs to the job's customer. A contact id
     * from the previous customer's list, or from another organization, must
     * not be written to the job (TASK-458).
     */
    protected function truckDriverBelongsTo(int|string|null $contactId, int|string|null $customerId): bool
    {
        if (! $contactId) {
            return true;
        }

        return $customerId
            && CustomerContact::whereKey($contactId)->where('customer_id', $customerId)->exists();
    }

    /**
     * Whether another live job in the organization already carries this job
     * number (TASK-458). Case- and whitespace-insensitive, like the importer.
     */
    protected function jobNumberTaken(int $organizationId, ?string $jobNo, ?int $exceptJobId = null): bool
    {
        $jobNo = trim((string) $jobNo);

        if ($jobNo === '') {
            return false;
        }

        return \App\Models\PilotCarJob::where('organization_id', $organizationId)
            ->whereRaw('LOWER(TRIM(job_no)) = ?', [mb_strtolower($jobNo)])
            ->when($exceptJobId, fn ($q) => $q->whereKeyNot($exceptJobId))
            ->exists();
    }

    /**
     * @return int|null The contact id to store as default_truck_driver_id,
     *                  or null when nothing was typed.
     */
    protected function resolveTruckDriverContact(?string $name, ?string $phone, ?int $customerId, int $organizationId): ?int
    {
        $name = trim((string) $name);

        if ($name === '' || ! $customerId) {
            return null;
        }

        $phone = trim((string) $phone) ?: null;

        // Match on name within the customer, not name+phone: re-typing a known
        // driver without their number should still find them rather than
        // silently creating a second copy of the same person.
        $existing = CustomerContact::where('customer_id', $customerId)
            ->where('organization_id', $organizationId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            // Fill in a number we did not have before, but never overwrite one.
            if ($phone && ! $existing->phone) {
                $existing->update(['phone' => $phone]);
            }

            return $existing->id;
        }

        return CustomerContact::create([
            'name' => $name,
            'phone' => $phone,
            'customer_id' => $customerId,
            'organization_id' => $organizationId,
        ])->id;
    }
}
