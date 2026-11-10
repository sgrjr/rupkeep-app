<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per text, email or push the app hands to a transport (TASK-498), so
 * a manager can see who was told what, when, and whether it went out. Nothing
 * was recorded before this table, so history starts at deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient')->nullable();
            $table->string('channel', 10);
            $table->string('kind', 40)->nullable();
            $table->string('subject')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('status', 20);
            $table->nullableMorphs('related');
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
