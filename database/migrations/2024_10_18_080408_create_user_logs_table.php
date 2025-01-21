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
            $table->string('vehicle_position')->nullable();
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

            $table->foreignId('organization_id')
            ->constrained()
            ->cascadeOnUpdate()
            ->cascadeOnDelete()
            ->nullable();

            $table->foreignId('job_id')
            ->constrained(table: 'pilot_car_jobs')
            ->cascadeOnUpdate()
            ->cascadeOnDelete()
            ->nullable();

            $table->foreignId('car_driver_id')
            ->constrained(table: 'users')
            ->cascadeOnUpdate()
            ->cascadeOnDelete()
            ->nullable();

            $table->foreignId('truck_driver_id')
            ->constrained(table: 'customer_contacts')
            ->cascadeOnUpdate()
            ->cascadeOnDelete()
            ->nullable();

            $table->foreignId('vehicle_id')
            ->constrained()
            ->cascadeOnUpdate()
            ->cascadeOnDelete()
            ->nullable();
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
