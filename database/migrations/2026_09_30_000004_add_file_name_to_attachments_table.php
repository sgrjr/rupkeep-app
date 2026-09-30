<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-454. Attachments are now stored under `{uuid}.{ext}` on the one
 * private disk, with the name the user gave kept in `file_name`. Rows
 * written before this carry either a relative path (log page) or an absolute
 * `storage_path(...)` (job page); both are rewritten here to the relative
 * form so a single reader serves all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('file_name')->nullable()->after('location');
        });

        $bases = array_map(
            fn (string $base) => rtrim(str_replace('\\', '/', $base), '/') . '/',
            [storage_path('app/private'), storage_path('app')]
        );

        foreach (DB::table('attachments')->select('id', 'location')->cursor() as $row) {
            $location = str_replace('\\', '/', (string) $row->location);

            foreach ($bases as $base) {
                if (str_starts_with($location, $base)) {
                    $location = substr($location, strlen($base));
                    break;
                }
            }

            DB::table('attachments')->where('id', $row->id)->update([
                'location' => ltrim($location, '/'),
                'file_name' => basename($location),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('file_name');
        });
    }
};
