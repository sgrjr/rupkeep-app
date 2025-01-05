<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
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
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, User $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool{
        return $user->id === $model->id || ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }

    public function restoreAny(User $user, User $model): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->is_super;
    }

    public function updateRole(User $user, User $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }

    public function impersonate(User $user, User $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->is_super;
    }
}
