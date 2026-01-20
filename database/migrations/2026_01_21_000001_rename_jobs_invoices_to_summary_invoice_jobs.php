<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Renames jobs_invoices table to summary_invoice_jobs to make it explicit
     * that this pivot table is only used for summary invoices that link to multiple jobs.
     * Single invoices use the pilot_car_job_id foreign key directly on the invoices table.
     */
    public function up(): void
    {
        Schema::rename('jobs_invoices', 'summary_invoice_jobs');
        
        // Add index on invoice_id for better query performance
        Schema::table('summary_invoice_jobs', function (Blueprint $table) {
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('summary_invoice_jobs', function (Blueprint $table) {
            $table->dropIndex(['invoice_id']);
        });
        
        Schema::rename('summary_invoice_jobs', 'jobs_invoices');
    }
};
