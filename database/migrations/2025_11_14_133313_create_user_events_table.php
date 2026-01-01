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
        Schema::create('user_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->string('url')->nullable();
            $table->string('type')->index(); // e.g., 'error', 'warning', 'info', 'action'
            $table->string('severity')->default('info')->index(); // 'info', 'warning', 'error'
            $table->text('context')->nullable(); // JSON or text for stack traces, messages, etc.
            $table->string('ip', 45)->nullable(); // IPv6 can be up to 45 chars
            $table->timestamps();
            
            $table->index(['type', 'severity']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_events');
    }
};
