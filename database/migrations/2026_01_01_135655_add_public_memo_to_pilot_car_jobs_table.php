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
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->text('public_memo')->nullable()->after('memo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->dropColumn('public_memo');
        });
    }
};
