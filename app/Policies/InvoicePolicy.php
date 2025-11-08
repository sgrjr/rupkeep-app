<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invoice;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_super || $user->isAdmin() || $user->isManager() || $user->isCustomer();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $model): bool
    {
        if ($user->is_super || $user->isAdmin() || $user->isManager()) {
            return $user->organization_id === $model->organization_id || $user->is_super;
        }

        if ($user->isCustomer() && $user->customer_id === $model->customer_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Invoice $model): bool
    {
        return $user->isAdmin() || $user->is_super;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $model): bool
    {
        return $user->isAdmin() || $user->is_super;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $model): bool
    {
        return $user->isAdmin() || $user->is_super;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $model): bool
    {
        return $user->is_super;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $model): bool
    {
        return $user->is_super;
    }

}
