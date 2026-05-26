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

    public function portalIndex()
    {
        $user = auth()->user();
        if (!$user || (!$user->isCustomer() && !$user->isAdmin() && !$user->is_super)) {
            abort(403);
        }

        return view('customer-portal.tasks.index');
    }

    public function portalShow(Task $task)
    {
        $user = auth()->user();
        if (!$user || (!$user->isCustomer() && !$user->isAdmin() && !$user->is_super)) {
            abort(403);
        }

        $this->authorize('view', $task);

        return view('customer-portal.tasks.show', ['task' => $task]);
    }

    // promoteFromFeedback() was removed when feedback was integrated into Dispatch:
    // FeedbackForm now creates a Task directly with status='triage' and a
    // 'source:feedback' label. Historical user_events rows are backfilled by
    // `php artisan dispatch:backfill-feedback`. See CLAUDE.md.
}
