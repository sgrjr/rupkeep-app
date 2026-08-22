<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-330: explode the single "Extra Charges" invoice row into named line items.
 *
 * A log carried one unlabeled `extra_charge` float. Every such amount across a
 * job's logs was summed into one scalar and printed as "Extra Charges $340.00",
 * so a one-off expense — renting special equipment for an unusual job — reached
 * the customer as an unexplained lump with no way to bill it back legibly.
 *
 * Charges are a property of a driver log, always. The job view and invoice edit
 * screens are convenience paths that write here; there is no second store, which
 * is what keeps a rebuilt invoice reproducing exactly the same charges.
 *
 * The backfill below moves every existing non-zero `extra_charge` into one child
 * row and nulls the column. Both halves matter: without the move the amount
 * disappears from invoices, and without the null it would be counted twice once
 * getExtraCharges() starts summing children as well.
 *
 * The column is deliberately NOT dropped, so down() can fold the children back
 * into it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_extra_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_log_id')->constrained('user_logs')->cascadeOnDelete();

            // Tenancy in this app is manual — there is no global scope, so every
            // record carries its own organization_id (see TenantIsolationTest).
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('description');
            $table->float('amount')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_log_id', 'sort_order']);
        });

        // "Extra Charges" is the exact label render.blade.php prints today, so
        // existing invoices read identically after the move.
        DB::table('user_logs')
            ->whereNotNull('extra_charge')
            ->where('extra_charge', '<>', 0)
            ->orderBy('id')
            ->chunkById(200, function ($logs) {
                $now = now();
                $rows = [];

                foreach ($logs as $log) {
                    $rows[] = [
                        'user_log_id' => $log->id,
                        'organization_id' => $log->organization_id,
                        'description' => 'Extra Charges',
                        'amount' => (float) $log->extra_charge,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('log_extra_charges')->insert($rows);
                }

                DB::table('user_logs')
                    ->whereIn('id', collect($logs)->pluck('id'))
                    ->update(['extra_charge' => null]);
            });
    }

    public function down(): void
    {
        // Fold the children back into the legacy column so no money is lost.
        $totals = DB::table('log_extra_charges')
            ->selectRaw('user_log_id, SUM(amount) as total')
            ->groupBy('user_log_id')
            ->get();

        foreach ($totals as $row) {
            DB::table('user_logs')
                ->where('id', $row->user_log_id)
                ->update(['extra_charge' => (float) $row->total]);
        }

        Schema::dropIfExists('log_extra_charges');
    }
};
