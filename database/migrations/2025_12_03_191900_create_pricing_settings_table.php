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
        Schema::create('pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key'); // e.g., 'rates.lead_chase_per_mile.rate_per_mile'
            $table->text('setting_value'); // JSON encoded value
            $table->string('setting_type')->default('string'); // string, number, boolean, json
            $table->string('category')->default('rates'); // rates, charges, cancellation, payment_terms
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Unique constraint: one setting per key per organization
            $table->unique(['organization_id', 'setting_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
