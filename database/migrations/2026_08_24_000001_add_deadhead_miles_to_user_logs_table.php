<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the `is_deadhead` boolean with the two explicit quantities the
 * deadhead charge actually needs (TASK-354).
 *
 * The legacy flag was a boolean standing in for a number nobody collected:
 * it could not say HOW FAR the driver deadheaded, and it silently carried a
 * second payload — a human judgement about whether that approach should be
 * billed at all. Splitting it makes both explicit:
 *
 *   dead_head_driven — what the vehicle actually drove to reach the pickup.
 *                      Recorded always, whether or not a cent of it is billed.
 *   dead_head_billed — what the customer is charged for. Opt-in: 0 unless a
 *                      human decides otherwise, capped by policy at
 *                      driven - free_miles so the published "first 75 miles
 *                      free" can never be billed through.
 *
 * `is_deadhead` is deliberately NOT dropped here. Nothing reads it after this
 * release, but the CSV importer still records it as legacy provenance and
 * keeping the column makes this migration reversible without data loss.
 */
return new class extends Migration
{
    /**
     * Approach legs beyond this are odometer typos, not driving. The worst row
     * in production implies a 190,065-mile approach; backfilling that would
     * hand someone a six-figure suggested charge.
     */
    private const MAX_PLAUSIBLE_APPROACH = 1000;

    public function up(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->decimal('dead_head_driven', 10, 1)->nullable()->after('billable_miles');
            $table->decimal('dead_head_billed', 10, 1)->nullable()->after('dead_head_driven');
        });

        $this->backfillDriven();
    }

    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropColumn(['dead_head_driven', 'dead_head_billed']);
        });
    }

    /**
     * Seed `dead_head_driven` from the approach leg the four odometer readings
     * already describe: start_mileage -> start_job_mileage is, by definition,
     * the drive to the pickup. 95% of production logs carry all four readings
     * in ascending order, so this recovers years of deadhead history that was
     * previously only expressible as a yes/no.
     *
     * `dead_head_billed` is left null on purpose. Billing is opt-in, and
     * backfilling it would retroactively invoice ~$22,834 of approach miles
     * nobody agreed to charge.
     */
    private function backfillDriven(): void
    {
        DB::table('user_logs')
            ->whereNotNull('start_mileage')
            ->whereNotNull('end_mileage')
            ->whereNotNull('start_job_mileage')
            ->whereNotNull('end_job_mileage')
            ->orderBy('id')
            ->chunkById(500, function ($logs) {
                foreach ($logs as $log) {
                    $start = (float) $log->start_mileage;
                    $jobStart = (float) $log->start_job_mileage;
                    $jobEnd = (float) $log->end_job_mileage;
                    $end = (float) $log->end_mileage;

                    // Readings out of order describe no coherent trip at all,
                    // so no segment of them can be trusted as an approach.
                    if (! ($start <= $jobStart && $jobStart <= $jobEnd && $jobEnd <= $end)) {
                        continue;
                    }

                    $approach = $jobStart - $start;

                    if ($approach <= 0 || $approach > self::MAX_PLAUSIBLE_APPROACH) {
                        continue;
                    }

                    DB::table('user_logs')
                        ->where('id', $log->id)
                        ->update(['dead_head_driven' => $approach]);
                }
            });
    }
};
