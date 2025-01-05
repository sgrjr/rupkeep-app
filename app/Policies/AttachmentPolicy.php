<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attachment;

class AttachmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function download(User $user, Attachment $attachment): bool
    {
        return $user->is_super || $attachment->organization_id === $user->organization_id;
    }

}
