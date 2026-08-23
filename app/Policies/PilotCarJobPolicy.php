<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PilotCarJob as Job;

class PilotCarJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuper() || $user->isEmployee();
    }

    /**
     * Every organization's jobs at once, at /jobs.
     *
     * Deliberately narrower than viewAny, which admits any employee who may see
     * their OWN organization's jobs at /my/jobs. Crossing that boundary is a
     * super-user ability and nothing else, so it is named separately rather
     * than left to a controller to remember -- forgetting is what made a bare
     * GET /jobs list the whole platform (TASK-390).
     */
    public function viewAcrossOrganizations(User $user): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Job $model): bool
    {
        return $user->organization_id === $model->organization_id || $user->isSuper();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Job $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Job $model): bool
    {
        return ($user->organization_id === $model->organization_id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Job $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->isAdmin()) || $user->isSuper();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Job $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->isAdmin()) || $user->isSuper();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Job $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->isAdmin()) || $user->isSuper();
    }

}
