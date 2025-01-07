<?php

namespace App\Policies;

use App\Models\CustomerContact;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CustomerContactPolicy
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
    public function view(User $user, CustomerContact $customer_contact): bool
    {
        return $user->organization_id === $customer_contact->organization_id || $user->organization->is_super;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, CustomerContact $customer_contact): bool
    {
        return ($user->organization_id === $customer_contact->organization_id && in_array($user->organization_role,['administrator','editor'])) || $user->organization->is_super;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomerContact $customer_contact): bool
    {
        return ($user->organization_id === $customer_contact->organization_id && in_array($user->organization_role,['administrator','editor','driver'])) || $user->organization->is_super;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomerContact $customer_contact): bool
    {
        return ($user->organization_id === $customer_contact->organization_id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomerContact $customer_contact): bool
    {
        return ($user->organization_id === $customer_contact->organization_id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomerContact $customer_contact): bool
    {
        return ($user->organization_id === $customer_contact->organization_id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }

    public function createCustomer(User $user, CustomerContact $customer_contact): bool
    {
        return ($user->organization_id === $customer_contact->organization_id && $user->organization_role === 'administrator') || $user->organization->is_super;
    }
    
}
