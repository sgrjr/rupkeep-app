<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'is_in_garage')) {
                // Whether the vehicle is currently parked/available at the garage
                // (as opposed to out on a job). Defaults to true — a new vehicle
                // is assumed to be in the garage until dispatched.
                $table->boolean('is_in_garage')->default(true)->after('is_in_service');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'is_in_garage')) {
                $table->dropColumn('is_in_garage');
            }
        });
    }
};
