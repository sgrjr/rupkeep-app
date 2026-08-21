<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-364. A driver could save their log but had no way to say "I'm done".
 * Job status was derived purely from whether an invoice existed, so nothing
 * told the office a job was ready to review and bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('approved_by_id');
            $table->unsignedBigInteger('completed_by_id')->nullable()->after('completed_at');

            // Drives the office's "ready to review" queue.
            $table->index(['organization_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'completed_at']);
            $table->dropColumn(['completed_at', 'completed_by_id']);
        });
    }
};
