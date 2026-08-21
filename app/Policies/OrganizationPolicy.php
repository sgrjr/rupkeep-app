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
        return $user->isSuper();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id || $user->isSuper();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->isAdmin()) || $user->isSuper();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can change the owner of the organization
     */
    public function updateOwner(User $user, Organization $organization): bool
    {
        return $user->isSuper();
    }
    
    public function createUser(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && $user->isAdmin()) || $user->isSuper();
    }

    public function createJob(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    public function createCustomer(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    public function createVehicle(User $user, Organization $organization): bool
    {
        return ($user->organization_id === $organization->id && ($user->isAdmin() || $user->isManager())) || $user->isSuper();
    }

    public function work(User $user, Organization $organization): bool
    {
        return $user->organization_id === $organization->id && $user->isEmployee();
    }

    public function resetOrganization(User $user, Organization $organization): bool
    {
        return $user->isSuper();
    }

}
