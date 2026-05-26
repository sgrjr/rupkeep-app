<?php

namespace App\Console\Commands;

use App\Models\Label;
use App\Models\Task;
use App\Models\UserEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchBackfillFeedback extends Command
{
    protected $signature = 'dispatch:backfill-feedback
        {--dry-run : Show what would change without writing}';

    protected $description = 'One-shot: convert legacy user_events (type=feedback) rows into Dispatch tasks. Idempotent — skips events that already have a promoted task.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $events = UserEvent::with('user')
            ->where('type', UserEvent::TYPE_FEEDBACK)
            ->orderBy('created_at')
            ->get();

        $this->line("Found {$events->count()} legacy feedback events.");

        $summary = ['created' => 0, 'skipped_already_promoted' => 0, 'errors' => 0];

        $run = function () use ($events, &$summary) {
            $label = Label::firstOrCreate(['name' => 'source:feedback'], ['color' => '#fb923c']);

            foreach ($events as $event) {
                try {
                    $existing = Task::where('promoted_from_user_event_id', $event->id)->first();
                    if ($existing) {
                        $summary['skipped_already_promoted']++;
                        continue;
                    }

                    $body = (string) ($event->context['feedback'] ?? $event->context['message'] ?? '');
                    if ($body === '') {
                        $summary['errors']++;
                        $this->warn("  event #{$event->id}: empty body, skipping");
                        continue;
                    }

                    $task = Task::create([
                        'code'              => Task::nextCode(),
                        'title'             => Str::limit($body, 80, '…'),
                        'description'       => $body,
                        'type'              => $event->severity === UserEvent::SEVERITY_ERROR ? 'bug' : 'feature',
                        'priority'          => 'medium',
                        'status'            => 'triage',
                        'is_public'         => false,
                        'organization_id'   => $event->user?->organization_id,
                        'submitter_user_id' => $event->user_id,
                        'promoted_from_user_event_id' => $event->id,
                    ]);

                    // Preserve original submission timestamp on the task
                    if ($event->created_at) {
                        $task->forceFill([
                            'created_at' => $event->created_at,
                            'updated_at' => $event->created_at,
                        ])->save();
                    }

                    $task->labels()->syncWithoutDetaching([$label->id]);

                    $summary['created']++;
                } catch (\Throwable $e) {
                    $this->error("  event #{$event->id}: " . $e->getMessage());
                    $summary['errors']++;
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
            $this->warn('Dry run — nothing persisted.');
        } else {
            DB::transaction($run);
        }

        $this->info('Backfill complete.');
        foreach ($summary as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        return self::SUCCESS;
    }
}
