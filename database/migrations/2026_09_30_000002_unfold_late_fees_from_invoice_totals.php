<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TASK-443. Until this release, "Apply to Invoice" wrote the late fee INTO
 * `values.total` (total = original_total + late_fee_amount) while also saving
 * the fee in `values.late_fees`. Invoice::calculateLateFees() then added the
 * saved fee on top of the already-inflated total, so every read billed the
 * fee twice.
 *
 * From now on `values.total` is the pre-fee amount and the fee is added at
 * read time. This puts existing rows on that footing: where the stored total
 * is exactly original_total + late_fee_amount (to the cent), the fee was
 * folded in and is taken back out. A total that no longer matches was edited
 * by hand after the apply and is left alone -- we cannot know what the admin
 * intended, and a wrong guess would be worse than the status quo.
 *
 * Idempotent: once unfolded, total == original_total, which no longer matches
 * original_total + fee, so a second run changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $unfolded = 0;
        $skipped = [];

        DB::table('invoices')
            ->whereNotNull('values')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$unfolded, &$skipped) {
                foreach ($rows as $row) {
                    $values = json_decode($row->values, true);

                    if (! is_array($values) || empty($values['late_fees']['applied_at'])) {
                        continue;
                    }

                    $fee = round((float) ($values['late_fees']['late_fee_amount'] ?? 0), 2);
                    $original = round((float) ($values['late_fees']['original_total'] ?? 0), 2);
                    $total = round((float) ($values['total'] ?? 0), 2);

                    if ($fee <= 0 || $original <= 0) {
                        continue;
                    }

                    if (abs($total - ($original + $fee)) > 0.005) {
                        // Already unfolded, or edited by hand since the apply.
                        if (abs($total - $original) > 0.005) {
                            $skipped[] = $row->id;
                        }
                        continue;
                    }

                    $values['total'] = $original;
                    // Recorded at apply time as the double-counted figure; no
                    // longer read anywhere and would only mislead.
                    unset($values['late_fees']['total_with_late_fees']);

                    DB::table('invoices')
                        ->where('id', $row->id)
                        ->update([
                            'values' => json_encode($values),
                        ]);

                    $unfolded++;
                }
            });

        Log::info('TASK-443: unfolded late fees from invoice totals', [
            'unfolded' => $unfolded,
            'left_alone_edited_after_apply' => $skipped,
        ]);
    }

    /**
     * Not reversible: folding the fee back in would recreate the defect, and
     * the code that reads these rows no longer expects it.
     */
    public function down(): void
    {
    }
};
