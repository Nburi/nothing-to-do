<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records which planned_date a day-plan has already been promoted to
     * "Heute" for, so PromoteDayPlansToToday fires once per plan date instead
     * of on every tick. Before this, `is_today = false` was the cron's only
     * idempotency guard — which also matched a task the user had deliberately
     * removed from Heute, so it jumped straight back.
     *
     * Additive and nullable (safe to roll back / deploy ahead of the code).
     * A moved plan (planned_date changes, this column doesn't) is naturally
     * eligible again on its new date.
     */
    public function up(): void
    {
        Schema::table('task_day_plans', function (Blueprint $table) {
            $table->date('promoted_for_date')->nullable()->after('planned_date');
        });

        // Every plan whose day has already arrived counts as handled: without
        // this, the first cron tick after deploy would re-promote everything
        // users had already removed from Heute — the very bug being fixed.
        DB::table('task_day_plans')
            ->whereDate('planned_date', '<=', now()->toDateString())
            ->update(['promoted_for_date' => DB::raw('planned_date')]);
    }

    public function down(): void
    {
        Schema::table('task_day_plans', function (Blueprint $table) {
            $table->dropColumn('promoted_for_date');
        });
    }
};
