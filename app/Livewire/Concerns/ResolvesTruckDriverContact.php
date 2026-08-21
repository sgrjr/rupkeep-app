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
