<?php

namespace App\Http\Controllers;

use App\Models\Task;

class TaskController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Task::class);

        return view('tasks.index');
    }

    public function board()
    {
        $this->authorize('viewAny', Task::class);

        return view('tasks.board');
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return view('tasks.show', ['task' => $task]);
    }

    /**
     * "My Requests" — every authenticated user's own submissions plus the
     * public roadmap.
     *
     * There is deliberately no role gate here (TASK-361). The feedback form is
     * open to any authenticated user and links them straight to the task it
     * created, so gating this on isCustomer/isAdmin/is_super meant a manager or
     * driver who submitted feedback got a 403 on their own submission. The
     * listing itself is scoped in {@see \App\Livewire\TaskList::render()} to
     * submitter_user_id plus public tasks, so opening the route exposes nothing
     * a user could not already see.
     */
    public function portalIndex()
    {
        if (! auth()->check()) {
            abort(403);
        }

        return view('customer-portal.tasks.index');
    }

    public function portalShow(Task $task)
    {
        if (! auth()->check()) {
            abort(403);
        }

        // TaskPolicy::view() is the authority: staff on the owning org, the
        // submitter, or anyone in the org when the task is public.
        $this->authorize('view', $task);

        return view('customer-portal.tasks.show', ['task' => $task]);
    }

    // promoteFromFeedback() was removed when feedback was integrated into Dispatch:
    // FeedbackForm now creates a Task directly with status='triage' and a
    // 'source:feedback' label. Historical user_events rows are backfilled by
    // `php artisan dispatch:backfill-feedback`. See CLAUDE.md.
}
