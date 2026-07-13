<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TasksImport extends Command
{
    protected $signature = 'tasks:import
        {--path=docs/tasks.jsonld : JSON-LD file to import (relative to base_path)}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Import tasks + labels from docs/tasks.jsonld. Upserts by code.';

    public function handle(): int
    {
        $path = base_path($this->option('path'));
        $dryRun = (bool) $this->option('dry-run');

        if (!is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $doc = json_decode(file_get_contents($path), true);
        if (!is_array($doc) || !isset($doc['tasks'])) {
            $this->error('Invalid JSON-LD: missing tasks array');
            return self::FAILURE;
        }

        $tasksData  = $doc['tasks'];
        $labelsData = $doc['labels'] ?? [];

        $this->line("Loaded " . count($tasksData) . " tasks, " . count($labelsData) . " labels from " . $this->option('path'));

        $summary = ['labels_created' => 0, 'labels_updated' => 0, 'tasks_created' => 0, 'tasks_updated' => 0, 'tasks_skipped' => 0, 'statuses_preserved' => 0, 'comments_added' => 0];

        $run = function () use ($tasksData, $labelsData, &$summary) {
            // Labels first
            foreach ($labelsData as $l) {
                $name = $l['name'] ?? null;
                if (!$name) continue;

                $existing = Label::where('name', $name)->first();
                if ($existing) {
                    $existing->update(['color' => $l['color'] ?? $existing->color, 'description' => $l['description'] ?? $existing->description]);
                    $summary['labels_updated']++;
                } else {
                    Label::create([
                        'name' => $name,
                        'color' => $l['color'] ?? null,
                        'description' => $l['description'] ?? null,
                    ]);
                    $summary['labels_created']++;
                }
            }

            $labelIdsByName = Label::pluck('id', 'name')->all();

            foreach ($tasksData as $t) {
                $code = $t['code'] ?? null;
                if (!$code) {
                    $summary['tasks_skipped']++;
                    continue;
                }

                $payload = [
                    'title' => $t['title'] ?? '(untitled)',
                    'description' => $t['description'] ?? null,
                    'type' => in_array($t['type'] ?? null, Task::TYPES, true) ? $t['type'] : 'feature',
                    'priority' => in_array($t['priority'] ?? null, Task::PRIORITIES, true) ? $t['priority'] : 'medium',
                    'status' => in_array($t['status'] ?? null, Task::STATUSES, true) ? $t['status'] : 'triage',
                    'is_public' => (bool) ($t['isPublic'] ?? false),
                ];

                $task = Task::where('code', $code)->first();

                if ($task) {
                    // A local status transition newer than the snapshot means unpushed
                    // work (dispatch:done etc.) — keep the local status instead of
                    // silently reverting it to what production last saw.
                    if ($payload['status'] !== $task->status && $this->localStatusIsNewer($task, $t)) {
                        $this->warn("  {$code}: keeping local status `{$task->status}` (unpushed transition is newer than snapshot; snapshot says `{$payload['status']}`)");
                        $payload['status'] = $task->status;
                        $summary['statuses_preserved']++;
                    }

                    $task->fill($payload);
                    if (isset($t['updatedAt'])) {
                        try { $task->updated_at = \Carbon\Carbon::parse($t['updatedAt']); } catch (\Throwable) {}
                    }
                    $task->save();
                    $summary['tasks_updated']++;
                } else {
                    $task = Task::create(['code' => $code] + $payload);
                    if (isset($t['createdAt'])) {
                        try { $task->created_at = \Carbon\Carbon::parse($t['createdAt']); $task->save(); } catch (\Throwable) {}
                    }
                    if (isset($t['updatedAt'])) {
                        try { $task->updated_at = \Carbon\Carbon::parse($t['updatedAt']); $task->save(); } catch (\Throwable) {}
                    }
                    $summary['tasks_created']++;
                }

                // Sync labels
                $labelIds = [];
                foreach (($t['labels'] ?? []) as $name) {
                    if (isset($labelIdsByName[$name])) {
                        $labelIds[] = $labelIdsByName[$name];
                    }
                }
                $task->labels()->sync($labelIds);

                // Merge comments: add snapshot comments we don't have yet, keep
                // local-only ones (replace-all used to destroy unpushed notes and
                // reset every author/timestamp — see TASK-332).
                if (!empty($t['comments'])) {
                    $existing = $task->comments()
                        ->get(['body', 'event_type'])
                        ->map(fn ($c) => $c->event_type . '|' . $c->body)
                        ->flip();

                    foreach ($t['comments'] as $c) {
                        $body = $c['body'] ?? '';
                        $eventType = $c['eventType'] ?? TaskComment::EVENT_COMMENT;

                        if (isset($existing[$eventType . '|' . $body])) {
                            continue;
                        }

                        $comment = $task->comments()->make([
                            'body' => $body,
                            'is_internal' => (bool) ($c['isInternal'] ?? false),
                            'sent_to_customer' => (bool) ($c['sentToCustomer'] ?? false),
                            'event_type' => $eventType,
                            'meta' => $c['meta'] ?? null,
                        ]);

                        if (!empty($c['author'])) {
                            $comment->user_id = User::where('email', $c['author'])->value('id');
                        }

                        if (!empty($c['createdAt'])) {
                            try { $comment->created_at = \Carbon\Carbon::parse($c['createdAt']); } catch (\Throwable) {}
                        }

                        $comment->save();
                        $summary['comments_added']++;
                    }
                }
            }
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $run();
            } finally {
                DB::rollBack();
            }
            $this->warn('Dry run — no changes persisted.');
        } else {
            DB::transaction($run);
        }

        $this->info('Import complete.');
        foreach ($summary as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        return self::SUCCESS;
    }

    /**
     * True when the local task carries a status transition the snapshot can't
     * know about — i.e. its latest status_change comment postdates the
     * snapshot's updatedAt. No updatedAt in the snapshot means we can't tell,
     * so the snapshot wins (pre-fix behaviour).
     */
    private function localStatusIsNewer(Task $task, array $snapshotTask): bool
    {
        if (empty($snapshotTask['updatedAt'])) {
            return false;
        }

        try {
            $snapshotUpdatedAt = \Carbon\Carbon::parse($snapshotTask['updatedAt']);
        } catch (\Throwable) {
            return false;
        }

        $lastTransition = $task->comments()
            ->where('event_type', TaskComment::EVENT_STATUS_CHANGE)
            ->latest('created_at')
            ->first();

        return $lastTransition && $lastTransition->created_at->gt($snapshotUpdatedAt);
    }
}
