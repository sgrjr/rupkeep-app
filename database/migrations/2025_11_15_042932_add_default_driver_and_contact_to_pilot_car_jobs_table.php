<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->foreignId('default_driver_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            
            $table->foreignId('default_truck_driver_id')
                ->nullable()
                ->after('default_driver_id')
                ->constrained('customer_contacts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->dropForeign(['default_driver_id']);
            $table->dropForeign(['default_truck_driver_id']);
            $table->dropColumn(['default_driver_id', 'default_truck_driver_id']);
        });
    }
};
