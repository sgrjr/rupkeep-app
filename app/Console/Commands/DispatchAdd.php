<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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

    public function handle(): int
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

        $submitter = $this->resolveSubmitter();

        $task = Task::create([
            'code'              => Task::nextCode(),
            'title'             => Str::limit(trim($this->argument('title')), 255, '…'),
            'description'       => $this->option('description'),
            'type'              => $type,
            'priority'          => $priority,
            'status'            => $status,
            'is_public'         => (bool) $this->option('public'),
            'organization_id'   => $submitter?->organization_id,
            'submitter_user_id' => $submitter?->id,
        ]);

        $labelNames = (array) $this->option('label');
        $labelIds = [];
        foreach ($labelNames as $name) {
            $name = trim($name);
            if ($name === '') continue;
            $label = Label::firstOrCreate(['name' => $name]);
            $labelIds[] = $label->id;
        }
        if (!empty($labelIds)) {
            $task->labels()->syncWithoutDetaching($labelIds);
        }

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

        return User::whereHas('organization', fn ($q) => $q->where('name', 'Reynolds Upkeep'))->first()
            ?? User::orderBy('id')->first();
    }
}
