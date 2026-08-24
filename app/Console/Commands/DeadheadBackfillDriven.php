<?php

namespace App\Console\Commands;

use App\Models\UserLog;
use Illuminate\Console\Command;

/**
 * TASK-354 backfill. Seeds `dead_head_driven` on logs that have none, from the
 * approach leg the odometer already describes: start_mileage to
 * start_job_mileage IS the drive to the pickup.
 *
 * This lives in a command rather than in the migration that added the columns
 * because a migration runs exactly once. Any log that arrives afterwards -- a
 * CSV import, a restored dump, real history landing on a database that was
 * carrying test data when the schema changed -- would never be reached by a
 * backfill buried in that migration, and would sit blank forever with its
 * deadhead miles visible in the odometer the whole time.
 *
 * Idempotent by construction: it only fills logs where the field is NULL, so a
 * value a human entered or corrected is never overwritten. Re-run it as often
 * as new data lands.
 *
 * It deliberately will NOT touch `dead_head_billed`. Billing is opt-in, and
 * inferring a charge from mileage is exactly the thing this whole change
 * exists to stop.
 */
class DeadheadBackfillDriven extends Command
{
    protected $signature = 'deadhead:backfill-driven
        {--write : Apply the changes (default is a dry run)}
        {--overwrite : Also recompute logs that already have a value (destroys manual corrections)}';

    protected $description = 'Seed dead_head_driven on logs that have none, from the odometer approach leg (start mileage to job start mileage).';

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $overwrite = (bool) $this->option('overwrite');

        if ($overwrite && ! $this->confirmOverwrite()) {
            return self::FAILURE;
        }

        $summary = [
            'filled' => 0,
            'already_set' => 0,
            'no_usable_readings' => 0,
        ];

        $query = UserLog::query()->orderBy('id');

        $query->chunkById(500, function ($logs) use ($write, $overwrite, &$summary) {
            foreach ($logs as $log) {
                $hasValue = $log->dead_head_driven !== null;

                if ($hasValue && ! $overwrite) {
                    $summary['already_set']++;
                    continue;
                }

                $suggested = $log->suggestedDeadHeadMiles();

                if ($suggested === null) {
                    // Readings missing, out of order, or describing an approach
                    // too long to be real. Leaving it null keeps "we do not
                    // know" distinct from "drove straight there".
                    $summary['no_usable_readings']++;
                    continue;
                }

                if ($hasValue && (float) $log->dead_head_driven === $suggested) {
                    $summary['already_set']++;
                    continue;
                }

                $summary['filled']++;

                if ($write) {
                    $log->update(['dead_head_driven' => $suggested]);
                }
            }
        });

        $this->table(
            ['Outcome', 'Logs'],
            [
                [$write ? 'Filled' : 'Would fill', $summary['filled']],
                ['Already recorded', $summary['already_set']],
                ['No usable odometer readings', $summary['no_usable_readings']],
            ]
        );

        if (! $write) {
            $this->warn('Dry run — nothing was written. Re-run with --write to apply.');
        } else {
            $this->info('Backfill applied. dead_head_billed was not touched; billing stays opt-in.');
        }

        return self::SUCCESS;
    }

    private function confirmOverwrite(): bool
    {
        if (! $this->input->isInteractive()) {
            $this->error('--overwrite discards manually entered deadhead miles and cannot run non-interactively.');

            return false;
        }

        return $this->confirm('--overwrite will replace deadhead miles a human may have entered by hand. Continue?', false);
    }
}
