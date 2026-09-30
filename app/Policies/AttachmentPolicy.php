<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attachment;
use App\Models\PilotCarJob;
use App\Models\UserLog;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    /**
     * Staff of the organization may download anything of theirs. A customer
     * may download only a PUBLIC attachment that sits on one of their own
     * jobs (directly, or on a log of one). Everyone else is refused; the org
     * check alone let portal users pull private paperwork (TASK-434).
     */
    public function download(User $user, Attachment $attachment): bool
    {
        if ($user->isSuper()) {
            return true;
        }

        if ($attachment->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isEmployee()) {
            return true;
        }

        return $user->isCustomer()
            && (bool) $attachment->is_public
            && $user->customer_id !== null
            && $this->customerIdOf($attachment) === $user->customer_id;
    }

    /**
     * Admins and managers of the organization, plus the driver whose log the
     * attachment is on.
     */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($user->isSuper()) {
            return true;
        }

        if ($attachment->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        $attachable = $attachment->attachable;

        return $attachable instanceof UserLog && $attachable->car_driver_id === $user->id;
    }

    private function customerIdOf(Attachment $attachment): ?int
    {
        $attachable = $attachment->attachable;

        if ($attachable instanceof PilotCarJob) {
            return $attachable->customer_id;
        }

        if ($attachable instanceof UserLog) {
            return $attachable->job?->customer_id;
        }

        return null;
    }

    public function updateVisibility(User $user, Attachment $attachment): bool
    {
        return $user->isSuper()
            || ($attachment->organization_id === $user->organization_id && ($user->isAdmin() || $user->isManager()));
    }
}
