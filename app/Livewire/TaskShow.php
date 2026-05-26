<?php

namespace App\Livewire;

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskShow extends Component
{
    use AuthorizesRequests;

    public Task $task;
    public bool $portal = false;

    // Editable fields (staff only)
    public string $status = '';
    public string $type = '';
    public string $priority = '';
    public ?int $assignee_user_id = null;
    public bool $is_public = false;
    /** @var array<int> */
    public array $label_ids = [];

    protected $listeners = ['commentAdded' => '$refresh'];

    public function mount(Task $task, bool $portal = false): void
    {
        $this->authorize('view', $task);

        $this->portal = $portal;
        $this->task = $task->load(['labels', 'submitter', 'assignee', 'organization', 'customer']);

        $this->status = $task->status;
        $this->type = $task->type;
        $this->priority = $task->priority;
        $this->assignee_user_id = $task->assignee_user_id;
        $this->is_public = (bool) $task->is_public;
        $this->label_ids = $task->labels->pluck('id')->all();
    }

    public function canEdit(): bool
    {
        return Auth::user() && Auth::user()->can('update', $this->task);
    }

    public function saveMeta(): void
    {
        $this->authorize('update', $this->task);

        $this->validate([
            'status' => 'required|in:' . implode(',', Task::STATUSES),
            'type' => 'required|in:' . implode(',', Task::TYPES),
            'priority' => 'required|in:' . implode(',', Task::PRIORITIES),
            'assignee_user_id' => 'nullable|exists:users,id',
            'is_public' => 'boolean',
            'label_ids' => 'array',
            'label_ids.*' => 'integer|exists:labels,id',
        ]);

        $changes = [];

        if ($this->task->status !== $this->status) {
            $changes[] = ['status', $this->task->status, $this->status];
            $this->task->status = $this->status;
        }
        if ($this->task->type !== $this->type) {
            $this->task->type = $this->type;
        }
        if ($this->task->priority !== $this->priority) {
            $this->task->priority = $this->priority;
        }
        if ($this->task->assignee_user_id !== $this->assignee_user_id) {
            $changes[] = ['assignee_user_id', $this->task->assignee_user_id, $this->assignee_user_id];
            $this->task->assignee_user_id = $this->assignee_user_id;
        }
        if ((bool) $this->task->is_public !== $this->is_public) {
            $changes[] = ['is_public', (bool) $this->task->is_public, $this->is_public];
            $this->task->is_public = $this->is_public;
        }

        $this->task->save();

        $oldLabelIds = $this->task->labels->pluck('id')->sort()->values()->all();
        $newLabelIds = collect($this->label_ids)->sort()->values()->all();

        if ($oldLabelIds !== $newLabelIds) {
            $this->task->labels()->sync($newLabelIds);
            $changes[] = ['labels', $oldLabelIds, $newLabelIds];
        }

        // Record a single system comment summarizing the change set
        if (!empty($changes)) {
            $messages = [];
            foreach ($changes as [$field, $from, $to]) {
                $messages[] = match ($field) {
                    'status' => "Status changed from `{$from}` to `{$to}`.",
                    'assignee_user_id' => 'Assignee updated.',
                    'is_public' => $to ? 'Marked public — visible to customer.' : 'Marked private — hidden from customer.',
                    'labels' => 'Labels updated.',
                    default => "Field `{$field}` updated.",
                };
            }

            $eventType = count($changes) === 1
                ? match ($changes[0][0]) {
                    'status' => TaskComment::EVENT_STATUS_CHANGE,
                    'assignee_user_id' => TaskComment::EVENT_ASSIGNEE_CHANGE,
                    'is_public' => TaskComment::EVENT_PUBLIC_TOGGLE,
                    'labels' => TaskComment::EVENT_LABEL_ADDED,
                    default => TaskComment::EVENT_COMMENT,
                }
                : TaskComment::EVENT_COMMENT;

            $this->task->recordEvent(
                $eventType,
                Auth::id(),
                ['changes' => $changes],
                implode(' ', $messages)
            );
        }

        $this->task->refresh()->load(['labels', 'submitter', 'assignee']);

        $this->dispatch('task-saved');
    }

    public function render()
    {
        $assigneeOptions = $this->canEdit()
            ? User::query()
                ->where(function ($q) {
                    $orgId = Auth::user()->organization_id;
                    $q->whereNull('organization_id')->orWhere('organization_id', $orgId);
                })
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'email'])
            : collect();

        return view('livewire.task-show', [
            'assigneeOptions' => $assigneeOptions,
            'allLabels' => Label::orderBy('name')->get(),
            'statuses' => Task::STATUSES,
            'types' => Task::TYPES,
            'priorities' => Task::PRIORITIES,
        ]);
    }
}
