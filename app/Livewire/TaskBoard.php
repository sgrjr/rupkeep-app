<?php

namespace App\Livewire;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class TaskBoard extends Component
{
    use AuthorizesRequests;

    /** Columns we render, in order. (Declined is hidden from the board.) */
    public const COLUMNS = ['triage', 'open', 'in_progress', 'verifying', 'done'];

    #[Url(as: 'type', except: '')]
    public string $typeFilter = '';

    #[Url(as: 'priority', except: '')]
    public string $priorityFilter = '';

    #[Url(as: 'label', except: '')]
    public string $labelFilter = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Task::class);
    }

    /**
     * Persist a drag-and-drop move. Called from the JS sortable handler.
     *
     * @param  string  $code        TASK-### code
     * @param  string  $toStatus    target status column
     * @param  array<int,string>  $orderedCodes  full list of codes in the target column after the drop
     */
    public function moveCard(string $code, string $toStatus, array $orderedCodes): void
    {
        if (!in_array($toStatus, self::COLUMNS, true)) {
            return;
        }

        $task = Task::where('code', $code)->first();
        if (!$task) return;
        $this->authorize('update', $task);

        $fromStatus = $task->status;

        if ($fromStatus !== $toStatus) {
            $task->status = $toStatus;
            $task->save();
            $task->recordEvent(
                \App\Models\TaskComment::EVENT_STATUS_CHANGE,
                Auth::id(),
                ['from' => $fromStatus, 'to' => $toStatus],
                "Status changed from `{$fromStatus}` to `{$toStatus}` (via board).",
            );
        }

        // Persist within-column ordering for every card in the target column
        foreach ($orderedCodes as $position => $orderedCode) {
            Task::where('code', $orderedCode)->update(['position' => $position]);
        }
    }

    public function render()
    {
        $user = Auth::user();

        $query = Task::query()->with(['labels', 'assignee']);

        if ($user && !$user->organization?->is_super) {
            $query->where(function (Builder $q) use ($user) {
                $q->whereNull('organization_id')->orWhere('organization_id', $user->organization_id);
            });
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

        $byStatus = $query
            ->orderBy('position')
            ->orderByRaw("CASE priority WHEN 'blocker' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 99 END")
            ->orderBy('code')
            ->get()
            ->groupBy('status');

        return view('livewire.task-board', [
            'columns' => self::COLUMNS,
            'byStatus' => $byStatus,
            'labels' => \App\Models\Label::orderBy('name')->get(),
            'types' => Task::TYPES,
            'priorities' => Task::PRIORITIES,
        ]);
    }
}
