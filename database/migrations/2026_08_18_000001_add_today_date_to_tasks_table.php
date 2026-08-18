<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // The local calendar day this task was flagged "today" FOR — distinct
            // from is_today itself, which is a live flag with no date attached.
            // Stamped only when a task newly ENTERS today (see Task::todayDateFor()),
            // so it survives untouched while the task simply stays flagged across
            // reorders, and lets ProgressStats ask "was day X's today-list fully
            // cleared" for any past day, not just infer it from the live board
            // state. Existing is_today=true rows get no retroactive value — there
            // is no way to know which day they were originally meant for.
            $table->date('today_date')->nullable()->after('is_today');

            // ProgressStats::todayListStatsByDay() groups by this per user.
            $table->index(['user_id', 'today_date']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'today_date']);
            $table->dropColumn('today_date');
        });
    }
};
