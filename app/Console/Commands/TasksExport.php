<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Task;
use Illuminate\Console\Command;

class TasksExport extends Command
{
    protected $signature = 'tasks:export
        {--path=docs/tasks.jsonld : JSON-LD output path (relative to base_path)}';

    protected $description = 'Export all tasks + labels + comments from the DB into a JSON-LD bridge file.';

    public function handle(): int
    {
        $path = base_path($this->option('path'));

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $tasks = Task::with(['labels', 'comments.user', 'submitter', 'assignee'])
            ->orderBy('code')
            ->get()
            ->map(fn (Task $t) => $this->taskToJsonLd($t))
            ->all();

        $labels = Label::orderBy('name')->get()->map(fn (Label $l) => [
            '@type' => 'Label',
            'name' => $l->name,
            'color' => $l->color,
            'description' => $l->description,
        ])->all();

        $doc = [
            '@context' => [
                '@vocab' => 'https://rupkeep.app/schema/tasks/v1#',
                'code' => '@id',
                'labels' => ['@container' => '@set'],
                'comments' => ['@container' => '@list'],
                'createdAt' => ['@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'],
                'updatedAt' => ['@type' => 'http://www.w3.org/2001/XMLSchema#dateTime'],
            ],
            '@type' => 'TaskCollection',
            'schemaVersion' => '1.0',
            'exportedAt' => now()->toIso8601String(),
            'exportedBy' => 'tasks:export',
            'tasks' => $tasks,
            'labels' => $labels,
        ];

        file_put_contents(
            $path,
            json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        $this->info("Wrote {$path}");
        $this->line("Tasks: " . count($tasks));
        $this->line("Labels: " . count($labels));

        return self::SUCCESS;
    }

    private function taskToJsonLd(Task $t): array
    {
        return [
            '@type' => 'Task',
            'code' => $t->code,
            'title' => $t->title,
            'description' => $t->description,
            'type' => $t->type,
            'priority' => $t->priority,
            'status' => $t->status,
            'isPublic' => (bool) $t->is_public,
            'exceptionSignature' => $t->exception_signature,
            'labels' => $t->labels->pluck('name')->all(),
            'submitter' => $t->submitter?->email,
            'assignee' => $t->assignee?->email,
            'createdAt' => optional($t->created_at)->toIso8601String(),
            'updatedAt' => optional($t->updated_at)->toIso8601String(),
            'comments' => $t->comments->map(fn ($c) => [
                '@type' => 'Comment',
                'body' => $c->body,
                'author' => $c->user?->email,
                'isInternal' => (bool) $c->is_internal,
                'sentToCustomer' => (bool) $c->sent_to_customer,
                'eventType' => $c->event_type,
                'meta' => $c->meta,
                'createdAt' => optional($c->created_at)->toIso8601String(),
            ])->all(),
        ];
    }
}
