<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 3.3: the evening nudge at a fixed time per weekday (R2, risk C2).
 *
 * - users.nudge_times: the user's local "HH:MM" per ISO weekday ({"1": "18:30", ...}).
 *   Null means "never set": no push, and the reset keeps being offered from 17:00
 *   (App\Services\Planning\EveningNudge::FALLBACK). A weekday missing from a set map
 *   gets no nudge.
 * - evenings.nudge_sent_at: tonight's nudge was sent (at most one per evening). Nothing
 *   else reads it; no counting, no streak.
 *
 * Additive and nullable: every existing user and evening keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('nudge_times')->nullable();
        });

        Schema::table('evenings', function (Blueprint $table) {
            $table->timestamp('nudge_sent_at')->nullable()->after('battery_asked_at');
        });
    }

    public function down(): void
    {
        Schema::table('evenings', function (Blueprint $table) {
            $table->dropColumn('nudge_sent_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('nudge_times');
        });
    }
};
