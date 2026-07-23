<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every jobs listing (index, dashboard, recent filter) orders by
     * scheduled_pickup_at; without an index MySQL filesorts the whole
     * table per page load (TASK-304).
     */
    public function up(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->index('scheduled_pickup_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->dropIndex(['scheduled_pickup_at']);
        });
    }
};
