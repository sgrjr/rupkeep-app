<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-380: drop the dead pilot_car_jobs.extra_charge column.
 *
 * It has been on the table since 2024_10_18_075621_create_pilot_car_jobs_table
 * and nothing has ever used it: it is absent from PilotCarJob::$fillable, no
 * code reads it (invoiceValues() and calculateTotalDue() included), and the CSV
 * importer's `extra_charge` header maps onto the driver LOG, not the job.
 *
 * That is not an oversight -- a job-level extra charge is not a thing. Extra
 * charges are a property of a driver log, and since TASK-330 each one is a
 * named `log_extra_charges` row rather than an unlabeled float. A nullable
 * column on jobs that looks like it holds the same thing is a trap for the next
 * person totalling a job.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pilot_car_jobs', 'extra_charge')) {
            return;
        }

        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->dropColumn('extra_charge');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('pilot_car_jobs', 'extra_charge')) {
            return;
        }

        // Restores the shape, not the contents. Nothing ever wrote to it, so
        // there are no contents to lose.
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->float('extra_charge')->nullable();
        });
    }
};
