<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->organization->is_super;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Vehicle $model): bool
    {
        return $user->organization_id === $model->id || $user->organization->is_super;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Vehicle $model): bool
    {
        return ($user->organization_id === $model->organization_id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Vehicle $model): bool
    {
        return ($user->organization_id === $model->id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Vehicle $model): bool
    {
        return ($user->organization_id === $model->id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Vehicle $model): bool
    {
        return ($user->organization_id === $model->id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Vehicle $model): bool
    {
        return ($user->organization_id === $model->id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

}
