<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
 *
 * This migration adds columns and nothing else. Seeding `dead_head_driven`
 * from the odometer lives in `php artisan deadhead:backfill-driven`, which is
 * idempotent and re-runnable, because a migration runs exactly once: data
 * loaded afterwards -- a CSV import, a restored dump, the real production
 * history arriving later -- would never be reached by a backfill buried here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->decimal('dead_head_driven', 10, 1)->nullable()->after('billable_miles');
            $table->decimal('dead_head_billed', 10, 1)->nullable()->after('dead_head_driven');
        });
    }

    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropColumn(['dead_head_driven', 'dead_head_billed']);
        });
    }

};
