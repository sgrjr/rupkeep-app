<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\DispatchTaskService;
use Illuminate\Console\Command;

class DispatchAdd extends Command
{
    protected $signature = 'dispatch:add
        {title : The task title (short)}
        {--type=feature : bug | feature | chore | debt | verify}
        {--priority=medium : blocker | high | medium | low}
        {--status=triage : triage | open | in_progress | verifying | done | declined}
        {--description= : Full task body (markdown). Use heredoc or quoted multi-line.}
        {--public : Mark visible to customers (default: private)}
        {--label=* : Label name(s) to attach; auto-created if missing. Repeatable.}
        {--submitter= : Submitter email (default: first super user)}';

    protected $description = 'Create a new task in the local Dispatch DB.';

    public function handle(DispatchTaskService $tasks): int
    {
        $type = $this->option('type');
        $priority = $this->option('priority');
        $status = $this->option('status');

        if (!in_array($type, Task::TYPES, true)) {
            $this->error("--type must be one of: " . implode(', ', Task::TYPES));
            return self::FAILURE;
        }
        if (!in_array($priority, Task::PRIORITIES, true)) {
            $this->error("--priority must be one of: " . implode(', ', Task::PRIORITIES));
            return self::FAILURE;
        }
        if (!in_array($status, Task::STATUSES, true)) {
            $this->error("--status must be one of: " . implode(', ', Task::STATUSES));
            return self::FAILURE;
        }

        $labelNames = array_values(array_filter(array_map('trim', (array) $this->option('label')), fn ($n) => $n !== ''));

        $task = $tasks->create([
            'title'       => $this->argument('title'),
            'description' => $this->option('description'),
            'type'        => $type,
            'priority'    => $priority,
            'status'      => $status,
            'is_public'   => (bool) $this->option('public'),
        ], $labelNames, $this->resolveSubmitter());

        $this->info("Created {$task->code}: {$task->title}");
        $this->line("  type: {$type}  ·  priority: {$priority}  ·  status: {$status}  ·  public: " . ($task->is_public ? 'yes' : 'no'));
        if ($labelNames) {
            $this->line('  labels: ' . implode(', ', $labelNames));
        }

        return self::SUCCESS;
    }

    protected function resolveSubmitter(): ?User
    {
        if ($email = $this->option('submitter')) {
            return User::where('email', $email)->first();
        }

        // null → the service falls back to its default submitter.
        return null;
    }
}
