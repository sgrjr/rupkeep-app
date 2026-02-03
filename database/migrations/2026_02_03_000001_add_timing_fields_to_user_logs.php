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
        Schema::table('user_logs', function (Blueprint $table) {
            // Clock in/out fields for driver time tracking
            $table->datetime('clock_in')->nullable()->after('ended_at');
            $table->datetime('clock_out')->nullable()->after('clock_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropColumn(['clock_in', 'clock_out']);
        });
    }
};
