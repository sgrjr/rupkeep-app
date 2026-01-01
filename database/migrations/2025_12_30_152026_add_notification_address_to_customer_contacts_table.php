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
        Schema::table('customer_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_contacts', 'notification_address')) {
                $table->string('notification_address')->nullable()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('customer_contacts', 'notification_address')) {
                $table->dropColumn('notification_address');
            }
        });
    }
};
