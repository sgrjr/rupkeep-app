<?php

namespace App\Services;

use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Central place to mint Dispatch tasks in the local DB.
 *
 * Shared by the `dispatch:add` CLI command and ExceptionCaptureService so both
 * create tasks the same way (code minting, submitter resolution, label
 * attachment) instead of duplicating the logic — see TASK-337.
 */
class DispatchTaskService
{
    /**
     * Create a task and attach any labels (auto-creating labels as needed).
     *
     * @param  array<string,mixed>  $attributes  Task attributes (title required).
     * @param  array<int,string>    $labelNames  Label names to attach.
     */
    public function create(array $attributes, array $labelNames = [], ?User $submitter = null): Task
    {
        $submitter ??= $this->resolveDefaultSubmitter();

        $attributes['title'] = Str::limit(trim((string) ($attributes['title'] ?? '')), 255, '…');
        $attributes['code'] ??= Task::nextCode();
        $attributes['type'] ??= 'feature';
        $attributes['priority'] ??= 'medium';
        $attributes['status'] ??= 'triage';
        $attributes['is_public'] = (bool) ($attributes['is_public'] ?? false);
        $attributes['organization_id'] ??= $submitter?->organization_id;
        $attributes['submitter_user_id'] ??= $submitter?->id;

        $task = Task::create($attributes);

        $this->attachLabels($task, $labelNames);

        return $task;
    }

    /**
     * Attach the named labels to a task, creating any that don't exist yet.
     *
     * @param  array<int,string>  $labelNames
     */
    public function attachLabels(Task $task, array $labelNames): void
    {
        $labelIds = [];
        foreach ($labelNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $labelIds[] = Label::firstOrCreate(['name' => $name])->id;
        }

        if (! empty($labelIds)) {
            $task->labels()->syncWithoutDetaching($labelIds);
        }
    }

    /**
     * The default submitter for tasks created without an explicit author:
     * the operating organization's first user, falling back to the lowest id.
     */
    public function resolveDefaultSubmitter(): ?User
    {
        return User::whereHas('organization', fn ($q) => $q->where('name', 'Reynolds Upkeep'))->first()
            ?? User::orderBy('id')->first();
    }
}
