<?php

namespace App\Livewire;

use App\Models\Label;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TaskList extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    #[Url(as: 'priority', except: '')]
    public string $priorityFilter = '';

    #[Url(as: 'label', except: '')]
    public string $labelFilter = '';

    #[Url(as: 'assignee', except: '')]
    public string $assigneeFilter = '';

    #[Url(as: 'pub', except: '')]
    public string $publicFilter = '';

    #[Url(as: 'sort', except: 'priority')]
    public string $sort = 'priority';

    public bool $portal = false;

    public function mount(bool $portal = false): void
    {
        $this->portal = $portal;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'statusFilter', 'typeFilter', 'priorityFilter', 'labelFilter', 'assigneeFilter', 'publicFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'priorityFilter', 'labelFilter', 'assigneeFilter', 'publicFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        $query = Task::query()->with(['labels', 'submitter', 'assignee']);

        if ($this->portal && $user) {
            // Customer: own submissions OR public tasks (scoped to their org if known)
            $query->where(function (Builder $q) use ($user) {
                $q->where('submitter_user_id', $user->id)
                  ->orWhere(function (Builder $sub) use ($user) {
                      $sub->where('is_public', true)
                          ->when($user->organization_id, fn ($w) => $w->where(function ($w2) use ($user) {
                              $w2->whereNull('organization_id')->orWhere('organization_id', $user->organization_id);
                          }));
                  });
            });
        } elseif ($user && !($user->organization?->is_super)) {
            // Staff: scope to own org (super sees all)
            $query->where(function (Builder $q) use ($user) {
                $q->whereNull('organization_id')->orWhere('organization_id', $user->organization_id);
            });
        }

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'like', $term)->orWhere('code', 'like', $term);
            });
        }

        if (in_array($this->statusFilter, Task::STATUSES, true)) {
            $query->where('status', $this->statusFilter);
        }
        if (in_array($this->typeFilter, Task::TYPES, true)) {
            $query->where('type', $this->typeFilter);
        }
        if (in_array($this->priorityFilter, Task::PRIORITIES, true)) {
            $query->where('priority', $this->priorityFilter);
        }
        if ($this->labelFilter !== '') {
            $name = $this->labelFilter;
            $query->whereHas('labels', fn (Builder $q) => $q->where('name', $name));
        }
        if (ctype_digit($this->assigneeFilter)) {
            $query->where('assignee_user_id', (int) $this->assigneeFilter);
        }
        if ($this->publicFilter === '1') {
            $query->where('is_public', true);
        } elseif ($this->publicFilter === '0') {
            $query->where('is_public', false);
        }

        $query = $this->applySort($query);

        return view('livewire.task-list', [
            'tasks' => $query->paginate(25),
            'labels' => Label::orderBy('name')->get(),
            'statuses' => Task::STATUSES,
            'types' => Task::TYPES,
            'priorities' => Task::PRIORITIES,
        ]);
    }

    private function applySort(Builder $query): Builder
    {
        return match ($this->sort) {
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'code' => $query->orderBy('code'),
            'title' => $query->orderBy('title'),
            'status' => $query->orderByRaw("CASE status
                WHEN 'in_progress' THEN 1
                WHEN 'open' THEN 2
                WHEN 'triage' THEN 3
                WHEN 'verifying' THEN 4
                WHEN 'done' THEN 5
                WHEN 'declined' THEN 6
                ELSE 99 END")->orderByDesc('updated_at'),
            default => $query->orderByRaw("CASE priority
                WHEN 'blocker' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
                ELSE 99 END")->orderBy('code'),
        };
    }
}
