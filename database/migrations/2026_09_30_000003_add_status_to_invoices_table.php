<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-480. An invoice had no status: the customer was emailed the moment it
 * was created, later edits were silent, deletion was forceDelete, and
 * "regenerate" meant "create a second one". Every existing invoice has been in
 * the customer's hands, so it starts as `sent` (or `paid`); new invoices start
 * as `draft` and reach the customer only on Send.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status', 16)->default('draft')->index()->after('paid_in_full');
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('sent_at');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
            $table->unsignedBigInteger('voided_by_id')->nullable()->after('voided_at');
            // The invoice this one was regenerated from (which is now void).
            $table->unsignedBigInteger('replaces_invoice_id')->nullable()->after('voided_by_id');
        });

        DB::table('invoices')->where('paid_in_full', true)->update([
            'status' => 'paid',
            'sent_at' => DB::raw('created_at'),
            'paid_at' => DB::raw('updated_at'),
        ]);

        DB::table('invoices')->where('paid_in_full', false)->update([
            'status' => 'sent',
            'sent_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['status', 'sent_at', 'paid_at', 'voided_at', 'voided_by_id', 'replaces_invoice_id']);
        });
    }
};
