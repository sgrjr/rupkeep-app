<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEmployee() || $user->isAdmin() || $user->isManager() || $user->organization?->is_super;
    }

    public function view(User $user, Task $task): bool
    {
        if ($this->isStaff($user, $task->organization_id)) {
            return true;
        }

        if ($task->submitter_user_id === $user->id) {
            return true;
        }

        if ($task->is_public && $user->organization_id === $task->organization_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isEmployee() || $user->isAdmin() || $user->isManager() || $user->organization?->is_super;
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isStaff($user, $task->organization_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->organization?->is_super;
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->isStaff($user, $task->organization_id) || $task->submitter_user_id === $user->id;
    }

    public function commentInternal(User $user, Task $task): bool
    {
        return $this->isStaff($user, $task->organization_id);
    }

    public function sendCustomerUpdate(User $user, Task $task): bool
    {
        return $this->isStaff($user, $task->organization_id);
    }

    public function manageLabels(User $user): bool
    {
        return $user->isAdmin() || $user->organization?->is_super;
    }

    protected function isStaff(User $user, ?int $taskOrgId): bool
    {
        if ($user->organization?->is_super) {
            return true;
        }

        if ($taskOrgId !== null && $user->organization_id !== $taskOrgId) {
            return false;
        }

        return $user->isAdmin() || $user->isManager() || $user->isEmployee();
    }
}
