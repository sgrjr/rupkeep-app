<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TASK-319: one-time email sign-in links.
 *
 * A login code is redeemed two ways now — typed into the verify form, or
 * clicked from an emailed link. Those need different secrets. `code` is 8
 * characters of [A-Z0-9] because a human retypes it; putting that in a URL
 * would make every user's code brute-forceable over an unauthenticated GET.
 *
 * `link_token` is the high-entropy half, generated per code and never shown
 * on screen. Both live on the same row, so the single `used_at` gives the
 * link and the code shared single-use semantics for free: redeeming either
 * one retires the other.
 *
 * Nullable so rows issued before this migration survive — they simply have
 * no clickable link and remain redeemable by typing the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->string('link_token', 64)->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('login_codes', function (Blueprint $table) {
            $table->dropUnique(['link_token']);
            $table->dropColumn('link_token');
        });
    }
};
