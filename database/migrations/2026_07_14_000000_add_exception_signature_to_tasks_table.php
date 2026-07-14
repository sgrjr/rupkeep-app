<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Stable fingerprint of an uncaught exception (class + normalized
            // message + top app frame). Used to dedupe auto-created bug tasks so
            // a recurring failure bumps one task instead of flooding the board.
            // See App\Services\ExceptionCaptureService (TASK-337).
            $table->string('exception_signature')->nullable()->after('promoted_from_user_event_id');
            $table->index(['exception_signature', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['exception_signature', 'status']);
            $table->dropColumn('exception_signature');
        });
    }
};
