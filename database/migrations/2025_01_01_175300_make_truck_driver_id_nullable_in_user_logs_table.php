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
        // For MySQL/MariaDB, we need to modify the column directly
        // First, get the constraint name dynamically
        $constraint = DB::selectOne("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'user_logs' 
            AND COLUMN_NAME = 'truck_driver_id' 
            AND CONSTRAINT_NAME != 'PRIMARY'
            LIMIT 1
        ");
        
        // Drop the foreign key constraint if it exists
        if ($constraint && isset($constraint->CONSTRAINT_NAME)) {
            DB::statement("ALTER TABLE `user_logs` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
        
        // Modify the column to be nullable
        DB::statement('ALTER TABLE `user_logs` MODIFY COLUMN `truck_driver_id` BIGINT UNSIGNED NULL');
        
        // Re-add the foreign key constraint
        DB::statement('ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_truck_driver_id_foreign` 
            FOREIGN KEY (`truck_driver_id`) REFERENCES `customer_contacts` (`id`) 
            ON DELETE CASCADE ON UPDATE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint
        DB::statement('ALTER TABLE `user_logs` DROP FOREIGN KEY `user_logs_truck_driver_id_foreign`');
        
        // Modify the column to be NOT NULL
        DB::statement('ALTER TABLE `user_logs` MODIFY COLUMN `truck_driver_id` BIGINT UNSIGNED NOT NULL');
        
        // Re-add the foreign key constraint
        DB::statement('ALTER TABLE `user_logs` ADD CONSTRAINT `user_logs_truck_driver_id_foreign` 
            FOREIGN KEY (`truck_driver_id`) REFERENCES `customer_contacts` (`id`) 
            ON DELETE CASCADE ON UPDATE CASCADE');
    }
};
