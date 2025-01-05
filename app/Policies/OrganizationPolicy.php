<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
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
    public function view(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id || $user->is_super;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->organization_role === 'administrator') || $user->is_super;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can change the owner of the organization
     */
    public function updateOwner(User $user, Organization $organization): bool
    {
        return $user->is_super;
    }
    
    public function createUser(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->organization_role === 'administrator') || $user->is_super;
    }

    public function createJob(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->organization_role === 'administrator') || $user->is_super;
    }

    public function createCustomer(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->organization_role === 'administrator') || $user->is_super;
    }

    public function createVehicle(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->organization_role === 'administrator') || $user->is_super;
    }

    public function work(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->organization_role === 'driver');
    }
}
