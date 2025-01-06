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
        if(!Schema::hasColumn('customer_contacts', 'email')) {
            Schema::table('customer_contacts', function (Blueprint $table) {
                $table->string('email')->nullable();
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
        if(Schema::hasColumn('customer_contacts', 'email')){
            Schema::table('email', function (Blueprint $table) {
                $table->dropColumn('customer_contacts');
            });
        }
    }
};
