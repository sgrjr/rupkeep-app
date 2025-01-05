<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!Schema::hasColumn('user_logs', 'vehicle_position')) {
            Schema::table('user_logs', function (Blueprint $table) {
                $table->string('vehicle_position')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if(Schema::hasColumn('user_logs', 'vehicle_position')){
            Schema::table('user_logs', function (Blueprint $table) {
                $table->dropColumn('vehicle_position');
            });
        }
    }
};
