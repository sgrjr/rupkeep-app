<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;

class DispatchNext extends Command
{
    protected $signature = 'dispatch:next
        {--type= : Filter by type (bug, feature, chore, debt, verify)}
        {--label= : Filter by label name}
        {--json : Emit machine-readable JSON instead of human text}';

    protected $description = 'Show the single highest-priority open task for the agent to pick up next.';

    public function handle(): int
    {
        $query = Task::query()
            ->with('labels')
            ->whereIn('status', ['triage', 'open', 'in_progress'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'open' THEN 2 WHEN 'triage' THEN 3 ELSE 99 END")
            ->orderByRaw("CASE priority WHEN 'blocker' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 99 END")
            ->orderBy('code');

        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        if ($label = $this->option('label')) {
            $query->whereHas('labels', fn ($q) => $q->where('name', $label));
        }

        $task = $query->first();

        if (!$task) {
            $this->line($this->option('json') ? '{}' : 'No open tasks. Inbox zero — nice.');
            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'code' => $task->code,
                'title' => $task->title,
                'type' => $task->type,
                'priority' => $task->priority,
                'status' => $task->status,
                'is_public' => $task->is_public,
                'labels' => $task->labels->pluck('name')->all(),
                'description' => $task->description,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->line('<fg=cyan;options=bold>' . $task->code . '</> <fg=white>' . $task->title . '</>');
        $this->line('  priority: ' . $task->priority . '  ·  type: ' . $task->type . '  ·  status: ' . $task->status);
        if ($task->labels->isNotEmpty()) {
            $this->line('  labels: ' . $task->labels->pluck('name')->implode(', '));
        }
        if ($task->description) {
            $this->newLine();
            $this->line($task->description);
        }
        $this->newLine();
        $this->line('<fg=gray>Next steps:</>');
        $this->line('  <fg=gray>php artisan dispatch:show ' . $task->code . '</>      # full detail + thread');
        $this->line('  <fg=gray>php artisan dispatch:note ' . $task->code . ' "..."</>  # leave a finding');
        $this->line('  <fg=gray>php artisan dispatch:done ' . $task->code . '</>      # mark complete');

        return self::SUCCESS;
    }
}
