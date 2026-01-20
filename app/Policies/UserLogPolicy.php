<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserLog;

class UserLogPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserLog $model): bool
    {
        return $user->organization_id === $model->organization_id || $user->is_super;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->is_super;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager() || $user->isStandardEmployee())) || $user->is_super;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->is_super;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->is_super;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserLog $model): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can confirm the log.
     */
    public function confirm(User $user, UserLog $model): bool
    {
        // Managers can confirm any log in their organization
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->is_super;
    }

    /**
     * Determine whether the user can deny the log.
     */
    public function deny(User $user, UserLog $model): bool
    {
        // Managers can deny any log, assigned driver can deny their own log
        $canDeny = ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->is_super;
        
        // Also allow the assigned driver to deny
        if (!$canDeny && $model->car_driver_id === $user->id) {
            return true;
        }
        
        return $canDeny;
    }

}
