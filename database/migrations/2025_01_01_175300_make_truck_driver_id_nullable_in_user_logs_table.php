<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            // SQLite (and others): use the portable schema builder.
            Schema::table('user_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('truck_driver_id')->nullable()->change();
            });
            return;
        }

        // MySQL/MariaDB: drop the dynamically-named FK, alter the column, re-add the FK.
        $constraint = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'user_logs'
            AND COLUMN_NAME = 'truck_driver_id'
            AND CONSTRAINT_NAME != 'PRIMARY'
            LIMIT 1
        ");

        if ($constraint && isset($constraint->CONSTRAINT_NAME)) {
            DB::statement("ALTER TABLE `user_logs` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }

        DB::statement('ALTER TABLE `user_logs` MODIFY COLUMN `truck_driver_id` BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_truck_driver_id_foreign`
            FOREIGN KEY (`truck_driver_id`) REFERENCES `customer_contacts` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table('user_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('truck_driver_id')->nullable(false)->change();
            });
            return;
        }

        DB::statement('ALTER TABLE `user_logs` DROP FOREIGN KEY `user_logs_truck_driver_id_foreign`');
        DB::statement('ALTER TABLE `user_logs` MODIFY COLUMN `truck_driver_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_truck_driver_id_foreign`
            FOREIGN KEY (`truck_driver_id`) REFERENCES `customer_contacts` (`id`)
            ON DELETE CASCADE ON UPDATE CASCADE');
    }
};
