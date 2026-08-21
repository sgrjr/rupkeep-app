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
        return $user->isSuper();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserLog $model): bool
    {
        return $user->organization_id === $model->organization_id || $user->isSuper();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager() || $user->isStandardEmployee())) || $user->isSuper();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserLog $model): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can confirm the log.
     *
     * - Managers / admins in the same organization can confirm any log (e.g.
     *   when accepting on behalf of a driver who hasn't responded).
     * - The assigned driver themselves can confirm their own log so they can
     *   start editing without waiting for management to approve them.
     *
     * Symmetric with deny() — both sides of the accept/deny decision have
     * the same set of authorized actors.
     */
    public function confirm(User $user, UserLog $model): bool
    {
        $canManage = ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();

        if (!$canManage && $model->car_driver_id === $user->id) {
            return true;
        }

        return $canManage;
    }

    /**
     * Determine whether the user can deny the log.
     */
    public function deny(User $user, UserLog $model): bool
    {
        // Managers can deny any log, assigned driver can deny their own log
        $canDeny = ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
        
        // Also allow the assigned driver to deny
        if (!$canDeny && $model->car_driver_id === $user->id) {
            return true;
        }
        
        return $canDeny;
    }

    /**
     * Determine whether the user can mark the log complete (TASK-364).
     *
     * The assigned driver is the primary actor — completing is their way of
     * saying "I'm done, it's ready to bill". Managers and admins can also
     * complete on a driver's behalf (a driver who forgot, or is off shift).
     */
    public function complete(User $user, UserLog $model): bool
    {
        $canManage = ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();

        if (!$canManage && $model->car_driver_id === $user->id && $user->organization_id === $model->organization_id) {
            return true;
        }

        return $canManage;
    }

    /**
     * Determine whether the user can reopen a completed log.
     *
     * Deliberately narrower than complete(): once a driver has handed a job
     * off to the office, only the office decides it needs more work. Letting
     * the driver silently reopen would take the job back out of the review
     * queue after the office had already been told it was ready.
     */
    public function reopen(User $user, UserLog $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }
}
