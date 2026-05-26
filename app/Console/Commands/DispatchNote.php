<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Console\Command;

class DispatchNote extends Command
{
    protected $signature = 'dispatch:note
        {code : The task code, e.g. TASK-042}
        {body : The comment body (markdown ok)}
        {--public : Make the comment visible to the customer (default: internal)}
        {--author= : Author email (default: first super user)}';

    protected $description = 'Add a comment / discovery note to a task. Defaults to internal.';

    public function handle(): int
    {
        $task = Task::where('code', $this->argument('code'))->first();
        if (!$task) {
            $this->error("Task not found: {$this->argument('code')}");
            return self::FAILURE;
        }

        $author = $this->resolveAuthor();
        $isInternal = !$this->option('public');

        $comment = $task->comments()->create([
            'user_id'     => $author?->id,
            'body'        => $this->argument('body'),
            'is_internal' => $isInternal,
            'event_type'  => TaskComment::EVENT_COMMENT,
        ]);

        $this->info("Noted on {$task->code} (comment id={$comment->id}, " . ($isInternal ? 'internal' : 'public') . ').');
        return self::SUCCESS;
    }

    protected function resolveAuthor(): ?\App\Models\User
    {
        if ($email = $this->option('author')) {
            return \App\Models\User::where('email', $email)->first();
        }

        return \App\Models\User::whereHas('organization', fn ($q) => $q->where('name', 'Reynolds Upkeep'))->first()
            ?? \App\Models\User::orderBy('id')->first();
    }
}
