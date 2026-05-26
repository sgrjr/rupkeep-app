<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Task;
use App\Models\TaskComment;
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

        $summary = ['labels_created' => 0, 'labels_updated' => 0, 'tasks_created' => 0, 'tasks_updated' => 0, 'tasks_skipped' => 0];

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

                // Sync comments (replace-all for simplicity on import; non-destructive on subsequent runs that have no comments in jsonld)
                if (!empty($t['comments'])) {
                    $task->comments()->delete();
                    foreach ($t['comments'] as $c) {
                        $task->comments()->create([
                            'body' => $c['body'] ?? '',
                            'is_internal' => (bool) ($c['isInternal'] ?? false),
                            'event_type' => $c['eventType'] ?? TaskComment::EVENT_COMMENT,
                            'meta' => $c['meta'] ?? null,
                        ]);
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
}
