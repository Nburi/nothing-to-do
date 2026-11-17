<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // How far App\Console\Commands\EvaluateStreakDays has already
            // walked for this user — the last local calendar day it decided
            // an outcome for (or attempted to; a day with nothing to freeze
            // and no perfect result is still "evaluated", just left with no
            // row in streak_day_outcomes). Null means "never run yet": the
            // first tick only evaluates yesterday, deliberately not a deep
            // backfill into an account's whole history.
            $table->date('streak_last_evaluated_date')->nullable()->after('streak_risk_sent_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('streak_last_evaluated_date');
        });
    }
};
