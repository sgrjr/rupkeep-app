<?php

use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-366: make "super user" a property of a USER, not of an organization.
 *
 * Previously `is_super` existed only as an accessor on Organization that
 * matched the org name against 'Reynolds Upkeep'. That conflated two separate
 * ideas — application-wide platform access, and organization-scoped admin
 * rights (which organization_role already covers) — and meant every member of
 * that one org, including drivers and customer accounts, was intended to hold
 * cross-tenant access.
 *
 * It also never actually worked: 62 call sites read `$user->is_super`, which
 * was neither a column nor an accessor on User, so it evaluated to null
 * everywhere. Adding the column here makes those call sites correct AND
 * narrows the grant to named individuals.
 *
 * Deliberately NOT added to User::$fillable — this is a privilege flag and
 * must never be settable through mass assignment. Use `php artisan super:grant`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super')->default(false)->after('organization_role');
        });

        // Backfill: the admins of whichever organization the old accessor
        // treated as super. On this data that is the single platform owner,
        // which is the intended outcome — non-admin members of that org do not
        // inherit cross-tenant access.
        $superOrgIds = Organization::query()
            ->where('name', 'Reynolds Upkeep')
            ->pluck('id');

        if ($superOrgIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('organization_id', $superOrgIds)
                ->where('organization_role', 'admin')
                ->update(['is_super' => true]);
        }

        // Safety net: the bootstrap super user from config/setup.php, in case
        // the organization has since been renamed and the match above found
        // nothing. Without this a rename would leave nobody able to reach the
        // admin tools that grant the flag.
        $bootstrapEmail = config('setup.super_user.email');

        if (! empty($bootstrapEmail)) {
            DB::table('users')->where('email', $bootstrapEmail)->update(['is_super' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super');
        });
    }
};
