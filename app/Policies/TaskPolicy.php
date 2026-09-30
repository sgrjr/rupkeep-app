<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * The staff tracker (list and board). Admins and managers; drivers file
     * feedback through the form but do not work the queue (TASK-439).
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuper() || $user->isAdmin() || $user->isManager();
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
        return $user->isEmployee() || $user->isAdmin() || $user->isManager() || $user->isSuper();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isStaff($user, $task->organization_id);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->isSuper();
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
        return $user->isAdmin() || $user->isSuper();
    }

    /**
     * Who may work a task: a super user for any task; an admin or manager for
     * their own organization's. A task with no organization (the dev backlog
     * from dispatch:add and exception capture) belongs to the super user
     * alone; it used to be workable by staff of every tenant (TASK-439).
     */
    protected function isStaff(User $user, ?int $taskOrgId): bool
    {
        if ($user->isSuper()) {
            return true;
        }

        if ($taskOrgId === null || $user->organization_id !== $taskOrgId) {
            return false;
        }

        return $user->isAdmin() || $user->isManager();
    }
}
