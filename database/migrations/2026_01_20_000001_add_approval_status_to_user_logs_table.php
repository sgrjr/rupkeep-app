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
            $table->enum('approval_status', ['pending', 'confirmed', 'denied'])->default('pending')->after('billable_miles');
            $table->timestamp('approved_at')->nullable()->after('approval_status');
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_logs', function (Blueprint $table) {
            $table->dropForeign(['approved_by_id']);
            $table->dropColumn(['approval_status', 'approved_at', 'approved_by_id']);
        });
    }
};
