<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Console\Command;

class DispatchDone extends Command
{
    protected $signature = 'dispatch:done
        {code : The task code, e.g. TASK-042}
        {--ref= : Commit SHA / PR number / link to attach as the closing reference}
        {--note= : Optional message added to the closing comment}
        {--status=done : Final status (done | declined | verifying)}
        {--author= : Author email (default: first super user)}';

    protected $description = 'Mark a task complete (or declined / awaiting verification) with an optional ref + note.';

    public function handle(): int
    {
        $task = Task::where('code', $this->argument('code'))->first();
        if (!$task) {
            $this->error("Task not found: {$this->argument('code')}");
            return self::FAILURE;
        }

        $status = $this->option('status');
        if (!in_array($status, ['done', 'declined', 'verifying'], true)) {
            $this->error("Invalid status: {$status}. Must be one of: done, declined, verifying.");
            return self::FAILURE;
        }

        $previous = $task->status;
        if ($previous === $status) {
            $this->warn("Task {$task->code} is already in status `{$status}`.");
        }

        $author = $this->resolveAuthor();

        $task->status = $status;
        $task->save();

        $bodyParts = ["Status changed from `{$previous}` to `{$status}`."];
        if ($ref = $this->option('ref')) {
            $bodyParts[] = "Ref: {$ref}";
        }
        if ($note = $this->option('note')) {
            $bodyParts[] = $note;
        }

        $task->comments()->create([
            'user_id'    => $author?->id,
            'body'       => implode("\n\n", $bodyParts),
            'is_internal'=> false,
            'event_type' => TaskComment::EVENT_STATUS_CHANGE,
            'meta'       => ['from' => $previous, 'to' => $status, 'ref' => $this->option('ref')],
        ]);

        $this->info("{$task->code} → {$status}." . ($this->option('ref') ? "  ref={$this->option('ref')}" : ''));
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
