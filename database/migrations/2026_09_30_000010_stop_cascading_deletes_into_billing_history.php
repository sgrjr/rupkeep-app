<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a person, a vehicle or a customer contact must not delete the
 * driver logs they appear on, and deleting a job must not touch its invoices
 * (TASK-471).
 *
 * user_logs.car_driver_id / vehicle_id / truck_driver_id were ON DELETE
 * CASCADE (re-asserted in 2025_01_01_175300 and 2026_07_13_000002), so a
 * super user force-deleting a departed driver silently removed every log
 * that driver had ever filed, and with them the miles, tolls and hours the
 * invoices were built from. invoice_comments.user_id cascaded the same way.
 * invoices.pilot_car_job_id had no constraint at all; ~1,000 invoices point
 * at jobs that no longer exist, so the new constraint is added after those
 * are set to null.
 *
 * Every one of these columns is nullable (or made so here), and every
 * constraint becomes ON DELETE SET NULL: the row stays, the reference goes.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => [column => referenced table] */
    private array $nullOnDelete = [
        'user_logs' => [
            'car_driver_id' => 'users',
            'vehicle_id' => 'vehicles',
            'truck_driver_id' => 'customer_contacts',
            'completed_by_id' => 'users',
        ],
        'invoice_comments' => [
            'user_id' => 'users',
        ],
        'invoices' => [
            'pilot_car_job_id' => 'pilot_car_jobs',
            'voided_by_id' => 'users',
        ],
    ];

    public function up(): void
    {
        // invoice_comments.user_id was NOT NULL; a comment must be allowed to
        // outlive its author.
        Schema::table('invoice_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        foreach ($this->nullOnDelete as $table => $columns) {
            foreach ($columns as $column => $references) {
                // A reference to a row that is already gone would make the new
                // constraint impossible to add; the reference is the only thing
                // lost, and it was already dangling.
                DB::table($table)
                    ->whereNotNull($column)
                    ->whereNotIn($column, DB::table($references)->select('id'))
                    ->update([$column => null]);

                $this->dropForeignIfExists($table, $column);

                Schema::table($table, function (Blueprint $t) use ($column, $references) {
                    $t->foreign($column)->references('id')->on($references)->cascadeOnUpdate()->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        // Back to the cascades for the three log columns and the comment
        // author; the invoice constraints simply go, as before.
        foreach ($this->nullOnDelete as $table => $columns) {
            foreach ($columns as $column => $references) {
                $this->dropForeignIfExists($table, $column);

                if ($table === 'invoices' || $column === 'completed_by_id') {
                    continue;
                }

                Schema::table($table, function (Blueprint $t) use ($column, $references) {
                    $t->foreign($column)->references('id')->on($references)->cascadeOnUpdate()->cascadeOnDelete();
                });
            }
        }
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            // Names vary between the create migrations and later re-adds, so
            // look the constraint up rather than guess it.
            $constraint = DB::selectOne('
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ', [$table, $column]);

            if ($constraint && isset($constraint->CONSTRAINT_NAME)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
            }

            return;
        }

        $existing = collect(Schema::getForeignKeys($table))
            ->first(fn (array $fk) => ($fk['columns'] ?? []) === [$column]);

        if ($existing) {
            Schema::table($table, function (Blueprint $t) use ($existing, $column) {
                $t->dropForeign($existing['name'] ?? [$column]);
            });
        }
    }
};
