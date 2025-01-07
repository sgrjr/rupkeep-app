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
        if(!Schema::hasColumn('invoices', 'pilot_car_job_id')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('pilot_car_job_id')->nullable()->index();
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
        if(Schema::hasColumn('invoices', 'notification_address')){
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreignId('pilot_car_job_id');
            });
        }
    }
};
