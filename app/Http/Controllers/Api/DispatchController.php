<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Label;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
{
    /**
     * Export the canonical task list as a JSON-LD payload (same shape as
     * `php artisan tasks:export` writes to docs/tasks.jsonld).
     */
    public function snapshot(Request $request): JsonResponse
    {
        $this->ensureSuper($request);

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

        return response()->json([
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
            'exportedBy' => 'api:dispatch.snapshot',
            'exportedFor' => $request->user()?->email,
            'tasks' => $tasks,
            'labels' => $labels,
        ]);
    }

    /**
     * Accept a JSON-LD payload and upsert it (same logic as `php artisan tasks:import`).
     */
    public function apply(Request $request): JsonResponse
    {
        $this->ensureSuper($request);

        $doc = $request->json()->all();
        if (!is_array($doc) || !isset($doc['tasks']) || !is_array($doc['tasks'])) {
            return response()->json(['error' => 'Invalid JSON-LD: missing tasks array.'], 422);
        }

        $summary = ['labels_created' => 0, 'labels_updated' => 0, 'tasks_created' => 0, 'tasks_updated' => 0, 'comments_replaced' => 0];

        DB::transaction(function () use ($doc, &$summary) {
            foreach (($doc['labels'] ?? []) as $l) {
                $name = $l['name'] ?? null;
                if (!$name) continue;

                $existing = Label::where('name', $name)->first();
                if ($existing) {
                    $existing->update([
                        'color' => $l['color'] ?? $existing->color,
                        'description' => $l['description'] ?? $existing->description,
                    ]);
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

            foreach ($doc['tasks'] as $t) {
                $code = $t['code'] ?? null;
                if (!$code) continue;

                $payload = [
                    'title' => $t['title'] ?? '(untitled)',
                    'description' => $t['description'] ?? null,
                    'type' => in_array($t['type'] ?? null, Task::TYPES, true) ? $t['type'] : 'feature',
                    'priority' => in_array($t['priority'] ?? null, Task::PRIORITIES, true) ? $t['priority'] : 'medium',
                    'status' => in_array($t['status'] ?? null, Task::STATUSES, true) ? $t['status'] : 'triage',
                    'is_public' => (bool) ($t['isPublic'] ?? false),
                ];

                $existing = Task::where('code', $code)->first();
                if ($existing) {
                    $existing->fill($payload)->save();
                    $task = $existing;
                    $summary['tasks_updated']++;
                } else {
                    $task = Task::create(['code' => $code] + $payload);
                    $summary['tasks_created']++;
                }

                $labelIds = [];
                foreach (($t['labels'] ?? []) as $name) {
                    if (isset($labelIdsByName[$name])) {
                        $labelIds[] = $labelIdsByName[$name];
                    }
                }
                $task->labels()->sync($labelIds);

                if (!empty($t['comments']) && is_array($t['comments'])) {
                    $task->comments()->delete();
                    foreach ($t['comments'] as $c) {
                        $task->comments()->create([
                            'body' => $c['body'] ?? '',
                            'is_internal' => (bool) ($c['isInternal'] ?? false),
                            'sent_to_customer' => (bool) ($c['sentToCustomer'] ?? false),
                            'event_type' => $c['eventType'] ?? TaskComment::EVENT_COMMENT,
                            'meta' => $c['meta'] ?? null,
                        ]);
                        $summary['comments_replaced']++;
                    }
                }
            }
        });

        return response()->json([
            'status' => 'applied',
            'summary' => $summary,
            'appliedAt' => now()->toIso8601String(),
        ]);
    }

    protected function ensureSuper(Request $request): void
    {
        $user = $request->user();
        if (!$user || !$user->organization || !$user->organization->is_super) {
            abort(403, 'Dispatch API requires a super-user token.');
        }
    }

    protected function taskToJsonLd(Task $t): array
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
