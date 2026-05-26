<?php

namespace App\Livewire;

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TaskCreate extends Component
{
    use AuthorizesRequests;

    public bool $open = false;

    public string $title = '';
    public string $description = '';
    public string $type = 'feature';
    public string $priority = 'medium';
    public string $status = 'triage';
    public bool $is_public = false;
    /** @var array<int> */
    public array $label_ids = [];

    protected function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:20000',
            'type' => 'required|in:' . implode(',', Task::TYPES),
            'priority' => 'required|in:' . implode(',', Task::PRIORITIES),
            'status' => 'required|in:' . implode(',', Task::STATUSES),
            'is_public' => 'boolean',
            'label_ids' => 'array',
            'label_ids.*' => 'integer|exists:labels,id',
        ];
    }

    public function openModal(): void
    {
        $this->authorize('create', Task::class);
        $this->resetForm();
        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->authorize('create', Task::class);
        $this->validate();

        $user = Auth::user();

        $task = Task::create([
            'code' => Task::nextCode(),
            'title' => trim($this->title),
            'description' => $this->description !== '' ? $this->description : null,
            'type' => $this->type,
            'priority' => $this->priority,
            'status' => $this->status,
            'is_public' => $this->is_public,
            'organization_id' => $user->organization_id,
            'submitter_user_id' => $user->id,
        ]);

        if (!empty($this->label_ids)) {
            $task->labels()->sync($this->label_ids);
        }

        $this->closeModal();
        $this->dispatch('task-created', code: $task->code);
        $this->redirectRoute('tasks.show', $task, navigate: false);
    }

    private function resetForm(): void
    {
        $this->title = '';
        $this->description = '';
        $this->type = 'feature';
        $this->priority = 'medium';
        $this->status = 'triage';
        $this->is_public = false;
        $this->label_ids = [];
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.task-create', [
            'allLabels' => Label::orderBy('name')->get(),
            'statuses' => Task::STATUSES,
            'types' => Task::TYPES,
            'priorities' => Task::PRIORITIES,
        ]);
    }
}
