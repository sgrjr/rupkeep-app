<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'last_oil_change_at')) {
                $table->date('last_oil_change_at')->nullable()->after('odometer_updated_at');
            }

            if (! Schema::hasColumn('vehicles', 'next_oil_change_due_at')) {
                $table->date('next_oil_change_due_at')->nullable()->after('last_oil_change_at');
            }

            if (! Schema::hasColumn('vehicles', 'last_inspection_at')) {
                $table->date('last_inspection_at')->nullable()->after('next_oil_change_due_at');
            }

            if (! Schema::hasColumn('vehicles', 'next_inspection_due_at')) {
                $table->date('next_inspection_due_at')->nullable()->after('last_inspection_at');
            }

            if (! Schema::hasColumn('vehicles', 'current_user_id')) {
                $table->foreignId('current_user_id')
                    ->nullable()
                    ->after('next_inspection_due_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('vehicles', 'current_assignment_started_at')) {
                $table->timestamp('current_assignment_started_at')->nullable()->after('current_user_id');
            }

            if (! Schema::hasColumn('vehicles', 'current_assignment_notes')) {
                $table->text('current_assignment_notes')->nullable()->after('current_assignment_started_at');
            }

            if (! Schema::hasColumn('vehicles', 'is_in_service')) {
                $table->boolean('is_in_service')->default(true)->after('current_assignment_notes');
            }

            if (! Schema::hasColumn('vehicles', 'last_service_mileage')) {
                $table->integer('last_service_mileage')->nullable()->after('odometer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'current_user_id')) {
                $table->dropConstrainedForeignId('current_user_id');
            }

            foreach ([
                'last_oil_change_at',
                'next_oil_change_due_at',
                'last_inspection_at',
                'next_inspection_due_at',
                'current_assignment_started_at',
                'current_assignment_notes',
                'is_in_service',
                'last_service_mileage',
            ] as $column) {
                if (Schema::hasColumn('vehicles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

