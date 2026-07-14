<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds an ADDITIVE "mini" line-item charge that can stack on top of a
     * job's normal rate (including flat-rate jobs). This is distinct from
     * the `mini_flat_rate` rate_code, which remains a standalone rate.
     */
    public function up(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->decimal('mini_addon_amount', 10, 2)->nullable()->after('rate_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pilot_car_jobs', function (Blueprint $table) {
            $table->dropColumn('mini_addon_amount');
        });
    }
};
