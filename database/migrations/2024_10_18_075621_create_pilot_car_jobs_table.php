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
        Schema::create('pilot_car_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no')->nullable();
            $table->bigInteger('customer_id')->nullable();
            $table->timestamp('scheduled_pickup_at')->nullable();
            $table->timestamp('scheduled_delivery_at')->nullable();
            $table->string('load_no')->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('check_no')->nullable();
            $table->string('invoice_paid')->nullable();
            $table->string('invoice_no')->nullable();
            $table->string('rate_code')->nullable();
            $table->string('rate_value')->nullable();
            $table->float('extra_charge')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->binary('canceled_reason')->nullable();
            $table->binary('memo')->nullable();
            $table->foreignId('organization_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pilot_car_jobs');
    }
};
