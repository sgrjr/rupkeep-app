<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['bug', 'feature', 'chore', 'debt', 'verify'])->default('feature');
            $table->enum('priority', ['blocker', 'high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['triage', 'open', 'in_progress', 'verifying', 'done', 'declined'])->default('triage');
            $table->boolean('is_public')->default(false);
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('submitter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('promoted_from_user_event_id')->nullable()->constrained('user_events')->nullOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('is_public');
            $table->index(['customer_id', 'status']);
            $table->index(['assignee_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
