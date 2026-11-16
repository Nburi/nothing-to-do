<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (user, local calendar day) once that day's streak outcome
     * is DECIDED — 'perfect' or 'frozen' (see App\Services\ProgressStats).
     * Deliberately durable rather than always re-derived live: a day that
     * once reached "perfect" must stay perfect even if a task gets flagged
     * "today" for it later that same day (e.g. prepping something for
     * tomorrow while today is already fully cleared) — the first outcome
     * recorded for a date is final, never overwritten or deleted. A day with
     * no row here simply has no decided outcome yet (still in progress, for
     * today) or was a genuine break (for a past day — see
     * App\Console\Commands\EvaluateStreakDays).
     *
     * No retroactive backfill for existing accounts: same "clean count from
     * ship day" precedent as tasks.today_date itself.
     */
    public function up(): void
    {
        Schema::create('streak_day_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('outcome'); // 'perfect' | 'frozen'
            $table->string('reason')->nullable(); // 'today_list' | 'goal_empty_day' | 'full_clear' | 'empty_day'
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_day_outcomes');
    }
};
