<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * An action on a super user's account is only ever a super user's to take
     * (TASK-436). Org admins share an organization with the super user, so
     * every org-scoped rule below would otherwise admit them.
     */
    private function protectedTarget(User $user, User $model): bool
    {
        return $model->isSuper() && ! $user->isSuper();
    }

    private function sameOrgAdmin(User $user, User $model): bool
    {
        return $user->organization_id === $model->organization_id && $user->isAdmin();
    }

    /**
     * Nobody deletes the last admin of an organization; promote someone else
     * first. Otherwise the organization is left with no one who can manage it.
     */
    private function lastAdminOfOrganization(User $model): bool
    {
        return $model->isAdmin()
            && $model->organization_id !== null
            && User::where('organization_id', $model->organization_id)
                ->where('organization_role', User::ROLE_ADMIN)
                ->where('id', '!=', $model->id)
                ->doesntExist();
    }

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
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || ($user->organization_id === $model->organization_id && $user->isAdmin()) || $user->isSuper();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, User $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->isAdmin()) || $user->isSuper();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($this->protectedTarget($user, $model)) {
            return false;
        }

        return $user->id === $model->id || $this->sameOrgAdmin($user, $model) || $user->isSuper();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Deleting yourself, or the only admin, leaves nobody at the wheel.
        if ($user->id === $model->id || $this->protectedTarget($user, $model) || $this->lastAdminOfOrganization($model)) {
            return false;
        }

        return $this->sameOrgAdmin($user, $model) || $user->isSuper();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        if ($this->protectedTarget($user, $model)) {
            return false;
        }

        return $this->sameOrgAdmin($user, $model) || $user->isSuper();
    }

    public function restoreAny(User $user, User $model): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuper() && $user->id !== $model->id && ! $this->lastAdminOfOrganization($model);
    }

    public function updateRole(User $user, User $model): bool
    {
        if ($this->protectedTarget($user, $model)) {
            return false;
        }

        return $this->sameOrgAdmin($user, $model) || $user->isSuper();
    }

    public function impersonate(User $user, User $model): bool
    {
        if ($user->id === $model->id || $this->protectedTarget($user, $model)) {
            return false;
        }

        return $this->sameOrgAdmin($user, $model) || $user->isSuper();
    }
}
