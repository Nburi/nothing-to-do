<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The parts of a task that was split across several calendar days (the
     * Planer's "Aufteilen"). One row per part: which day, how many minutes of
     * work, and when it was ticked off. A task has at most one part per day —
     * the explicit short index name keeps the unique key under MariaDB's
     * 64-character identifier cap (SQLite never complains about that).
     *
     * task_day_plans stays untouched and stays "one row per task": for a
     * split task it mirrors the earliest still-open part (DayPlanner::
     * syncAnchor()), so every existing reader of task_day_plans — the
     * promote-to-Heute cron, the focus-timer suggestion, the Zeitplan strip,
     * the streak — keeps working without knowing parts exist.
     */
    public function up(): void
    {
        Schema::create('task_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->date('planned_date');
            $table->unsignedSmallInteger('minutes');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'planned_date'], 'task_sessions_task_day_unique');
            $table->index('planned_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_sessions');
    }
};
