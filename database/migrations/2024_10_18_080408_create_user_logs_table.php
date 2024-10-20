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
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('job_id');
            $table->bigInteger('car_driver_id')->nullable();
            $table->bigInteger('truck_driver_id')->nullable();
            $table->bigInteger('vehicle_id')->nullable();
            $table->string('truck_no')->nullable();
            $table->string('trailer_no')->nullable();
            $table->integer('start_mileage')->nullable();
            $table->integer('end_mileage')->nullable();
            $table->integer('start_job_mileage')->nullable();
            $table->integer('end_job_mileage')->nullable();
            $table->integer('extra_load_stops_count')->default(0);
            $table->boolean('load_canceled')->default(false);
            $table->boolean('is_deadhead')->default(false);
            $table->boolean('pretrip_check')->default(false);
            $table->float('wait_time_hours')->nullable();
            $table->float('tolls')->nullable();
            $table->float('gas')->nullable();
            $table->float('hotel')->nullable();
            $table->binary('memo')->nullable();
            $table->float('extra_charge')->nullable();
            $table->binary('maintenance_memo')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();          
            $table->timestamps();
            $table->foreignId('organization_id');
            $table->index('job_id');
            $table->index('car_driver_id');
            $table->index('truck_driver_id');
            $table->index('vehicle_id');

            $table->foreign('job_id')->references('id')->on('pilot_car_jobs')->onDelete('SET NULL');
            $table->foreign('car_driver_id')->references('id')->on('users')->onDelete('SET NULL');
            $table->foreign('truck_driver_id')->references('id')->on('customer_contacts')->onDelete('SET NULL');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
