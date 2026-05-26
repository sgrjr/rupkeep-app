<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;

class DispatchQueue extends Command
{
    protected $signature = 'dispatch:queue
        {--n=10 : Number of tasks to show}
        {--type= : Filter by type}
        {--priority= : Filter by priority}
        {--label= : Filter by label name}
        {--status=* : Restrict to these statuses (default: triage, open, in_progress)}';

    protected $description = 'List the next N tasks in priority order.';

    public function handle(): int
    {
        $n = max(1, (int) $this->option('n'));
        $statuses = $this->option('status') ?: ['triage', 'open', 'in_progress'];

        $query = Task::query()
            ->with('labels')
            ->whereIn('status', $statuses)
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'open' THEN 2 WHEN 'triage' THEN 3 WHEN 'verifying' THEN 4 ELSE 99 END")
            ->orderByRaw("CASE priority WHEN 'blocker' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 99 END")
            ->orderBy('code')
            ->limit($n);

        if ($t = $this->option('type'))     $query->where('type', $t);
        if ($p = $this->option('priority')) $query->where('priority', $p);
        if ($l = $this->option('label'))    $query->whereHas('labels', fn ($q) => $q->where('name', $l));

        $tasks = $query->get();

        if ($tasks->isEmpty()) {
            $this->info('No matching tasks.');
            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Pri', 'Type', 'Status', 'Title'],
            $tasks->map(fn (Task $t) => [
                $t->code,
                $t->priority,
                $t->type,
                $t->status,
                \Illuminate\Support\Str::limit($t->title, 70),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
