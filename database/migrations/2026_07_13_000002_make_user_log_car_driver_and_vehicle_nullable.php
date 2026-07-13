<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * car_driver_id and vehicle_id were chained ->constrained()->...->nullable()
 * in the create migration, but nullable() after constrained() is a no-op, so
 * both columns ended up NOT NULL despite the clear intent (and the
 * "(none selected)" option in the log editor UI). Any log save that sets them
 * to null then failed a NOT NULL constraint and was silently swallowed
 * (TASK-318). This finishes the job started for truck_driver_id in
 * 2025_01_01_175300.
 */
return new class extends Migration
{
    private array $columns = [
        'car_driver_id' => ['table' => 'users'],
        'vehicle_id' => ['table' => 'vehicles'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('user_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('car_driver_id')->nullable()->change();
                $table->unsignedBigInteger('vehicle_id')->nullable()->change();
            });
            return;
        }

        foreach ($this->columns as $column => $meta) {
            $this->dropForeignIfExists($column);
            DB::statement("ALTER TABLE `user_logs` MODIFY COLUMN `{$column}` BIGINT UNSIGNED NULL");
            DB::statement("ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_{$column}_foreign`
                FOREIGN KEY (`{$column}`) REFERENCES `{$meta['table']}` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('user_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('car_driver_id')->nullable(false)->change();
                $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
            });
            return;
        }

        foreach ($this->columns as $column => $meta) {
            $this->dropForeignIfExists($column);
            DB::statement("ALTER TABLE `user_logs` MODIFY COLUMN `{$column}` BIGINT UNSIGNED NOT NULL");
            DB::statement("ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_{$column}_foreign`
                FOREIGN KEY (`{$column}`) REFERENCES `{$meta['table']}` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    private function dropForeignIfExists(string $column): void
    {
        $constraint = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'user_logs'
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ", [$column]);

        if ($constraint && isset($constraint->CONSTRAINT_NAME)) {
            DB::statement("ALTER TABLE `user_logs` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }
};
