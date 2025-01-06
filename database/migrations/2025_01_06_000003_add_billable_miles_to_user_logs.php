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
        if(!Schema::hasColumn('user_logs', 'billable_miles')) {
            Schema::table('user_logs', function (Blueprint $table) {
                $table->string('billable_miles')->nullable();
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
        if(Schema::hasColumn('user_logs', 'notification_address')){
            Schema::table('user_logs', function (Blueprint $table) {
                $table->dropColumn('billable_miles');
            });
        }
    }
};
