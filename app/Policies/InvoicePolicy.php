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
        // The staff list at /my/invoices. Customers have their own portal
        // index; admitting them here listed the whole organization (TASK-434).
        return $user->isSuper() || $user->isAdmin() || $user->isManager();
    }

    /**
     * Every organization's invoices at once, at /invoices.
     *
     * Deliberately narrower than viewAny, which admits anyone who may see their
     * OWN organization's invoices. Crossing that boundary is a super-user
     * ability and nothing else, so it is named separately rather than left to a
     * controller to remember.
     */
    public function viewAcrossOrganizations(User $user): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $model): bool
    {
        if ($user->isSuper() || $user->isAdmin() || $user->isManager()) {
            return $user->organization_id === $model->organization_id || $user->isSuper();
        }

        // Same organization AND same customer: a customer id alone is not a
        // tenancy check (TASK-430).
        if ($user->isCustomer()
            && $user->organization_id === $model->organization_id
            && $user->customer_id !== null
            && $user->customer_id === $model->customer_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    /**
     * $model is optional because the Gate passes only the user when a caller
     * authorizes against the class rather than an instance — which is exactly
     * what the summary-invoice path does. With it required, every attempt to
     * create a summary died on an ArgumentCountError before reaching the
     * controller (TASK-371).
     */
    public function create(User $user, ?Invoice $model = null): bool
    {
        // Managers generate invoices from the job page and the invoice list
        // (the notification tests encode that), so they belong here as they
        // do in update(). Drivers and customers do not.
        return $user->isSuper() || $user->isAdmin() || $user->isManager();
    }

    /**
     * Grouping invoices into a summary stays an admin decision (TASK-371):
     * managers may raise single invoices but not restructure billing.
     */
    public function createSummary(User $user): bool
    {
        return $user->isSuper() || $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $model): bool
    {
        if ($user->isSuper()) {
            return true;
        }

        // Admins and managers of the invoice's own organization only. This
        // used to return true for ANY admin, so an org-A admin could edit,
        // delete and regroup org B's invoices (TASK-430).
        return ($user->isAdmin() || $user->isManager())
            && $user->organization_id === $model->organization_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $model): bool
    {
        return $user->isSuper()
            || ($user->isAdmin() && $user->organization_id === $model->organization_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $model): bool
    {
        return $user->isSuper();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $model): bool
    {
        return $user->isSuper();
    }

}
