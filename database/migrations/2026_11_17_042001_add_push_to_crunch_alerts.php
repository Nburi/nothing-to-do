<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 4.2: the early warning is pushed (R5, risk F7).
 *
 * - crunch_alerts.push_pending_since: a hard crunch was raised and not pushed yet. The push
 *   waits for the day window (08:00–21:00 local) and for the end of a running session; seeing
 *   the warning on the board first clears it (no push for what you already saw).
 * - crunch_alerts.pushed_at: the last push, so there is at most one a local day.
 *
 * Additive and nullable: existing alert rows keep working unchanged and are simply "nothing
 * pending". Nothing is pushed retroactively for warnings raised before this release.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crunch_alerts', function (Blueprint $table) {
            $table->timestamp('push_pending_since')->nullable()->after('cleared_at');
            $table->timestamp('pushed_at')->nullable()->after('push_pending_since');
        });
    }

    public function down(): void
    {
        Schema::table('crunch_alerts', function (Blueprint $table) {
            $table->dropColumn(['push_pending_since', 'pushed_at']);
        });
    }
};
