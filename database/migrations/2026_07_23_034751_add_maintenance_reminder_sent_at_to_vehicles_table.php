<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks when a vehicle was last included in a maintenance-due reminder
     * digest so the daily vehicles:send-maintenance-reminders command nags at
     * most once per week while a due/overdue condition persists (TASK-041).
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('maintenance_reminder_sent_at')->nullable()->after('next_inspection_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('maintenance_reminder_sent_at');
        });
    }
};
