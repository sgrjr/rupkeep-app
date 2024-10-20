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
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id')->nullable();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('memo')->nullable();
            $table->foreignId('organization_id');
            $table->index('customer_id');
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
