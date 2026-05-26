<?php

namespace App\Livewire;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskThread extends Component
{
    use AuthorizesRequests;

    public Task $task;
    public bool $portal = false;

    public string $body = '';
    public bool $is_internal = false;

    protected function rules(): array
    {
        return [
            'body' => 'required|string|min:1|max:5000',
            'is_internal' => 'boolean',
        ];
    }

    protected $listeners = ['task-saved' => '$refresh'];

    public function mount(Task $task, bool $portal = false): void
    {
        $this->authorize('view', $task);
        $this->portal = $portal;
        $this->task = $task;
    }

    public function save(): void
    {
        $this->authorize('comment', $this->task);

        $this->validate();

        $internal = $this->is_internal;
        if ($internal && !Auth::user()->can('commentInternal', $this->task)) {
            $internal = false;
        }

        $this->task->comments()->create([
            'user_id' => Auth::id(),
            'body' => trim($this->body),
            'is_internal' => $internal,
            'event_type' => TaskComment::EVENT_COMMENT,
        ]);

        $this->reset('body', 'is_internal');
        $this->dispatch('commentAdded');
    }

    public function sendCustomerUpdate(int $commentId): void
    {
        $this->authorize('sendCustomerUpdate', $this->task);

        $comment = $this->task->comments()->whereKey($commentId)->firstOrFail();

        if ($comment->is_internal) {
            return; // never email internal comments
        }

        // TASK-WIRE-NOTIFY: this is where Phase 12 will dispatch the TaskUpdate notification.
        // For now we just mark the comment as sent so the UI badge appears immediately.
        $comment->update(['sent_to_customer' => true]);

        $this->dispatch('commentAdded');
    }

    public function render()
    {
        $query = $this->task->comments()->with('user')->orderBy('created_at');

        if (!Auth::user()->can('commentInternal', $this->task)) {
            $query->where('is_internal', false);
        }

        return view('livewire.task-thread', [
            'comments' => $query->get(),
            'canCommentInternal' => Auth::user()->can('commentInternal', $this->task),
            'canSendCustomerUpdate' => Auth::user()->can('sendCustomerUpdate', $this->task),
        ]);
    }
}
