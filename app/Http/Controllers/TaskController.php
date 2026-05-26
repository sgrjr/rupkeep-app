<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserEvent;
use Illuminate\Http\Request;

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

    public function promoteFromFeedback(Request $request, UserEvent $userEvent)
    {
        $this->authorize('create', Task::class);

        if ($userEvent->type !== UserEvent::TYPE_FEEDBACK) {
            abort(422, 'Only feedback events can be promoted.');
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|in:' . implode(',', Task::TYPES),
            'priority' => 'nullable|in:' . implode(',', Task::PRIORITIES),
        ]);

        $body = $userEvent->context['feedback'] ?? $userEvent->context['message'] ?? '';
        $defaultTitle = \Illuminate\Support\Str::limit($body, 80, '…') ?: 'Promoted feedback';

        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => $request->input('title') ?: $defaultTitle,
            'description' => $request->input('description') ?: $body,
            'type' => $request->input('type') ?: ($userEvent->severity === 'error' ? 'bug' : 'feature'),
            'priority' => $request->input('priority') ?: 'medium',
            'status' => 'triage',
            'is_public' => false,
            'organization_id' => $userEvent->user?->organization_id,
            'submitter_user_id' => $userEvent->user_id,
            'promoted_from_user_event_id' => $userEvent->id,
        ]);

        $task->recordEvent(
            \App\Models\TaskComment::EVENT_PROMOTED,
            $request->user()->id,
            ['from_user_event_id' => $userEvent->id],
            'Promoted from feedback #' . $userEvent->id . '.'
        );

        return redirect()
            ->route('tasks.show', $task)
            ->with('status', "Promoted to {$task->code}.");
    }
}
