<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // How many tasks completed in one local calendar day counts as "hit
            // the daily goal" — drives the progress ring and one of the two
            // celebration triggers. See ProgressStats::celebrationFor().
            $table->unsignedTinyInteger('daily_task_goal')->default(5)->after('notify_event_upcoming');

            // Evening push if today still has open "Heute"-flagged tasks.
            $table->boolean('notify_daily_reminder')->default(false)->after('daily_task_goal');
            $table->string('daily_reminder_time', 5)->default('19:00')->after('notify_daily_reminder');
            $table->date('daily_reminder_sent_on')->nullable()->after('daily_reminder_time');

            // "Last call" push if the current streak would otherwise break today.
            // Fixed at 21:00 local (see User::STREAK_RISK_DUE_TIME) — not user
            // configurable, mirroring prepareReminderDueTime()'s "automatic" fallback.
            $table->boolean('notify_streak_risk')->default(false)->after('daily_reminder_sent_on');
            $table->date('streak_risk_sent_on')->nullable()->after('notify_streak_risk');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'daily_task_goal',
                'notify_daily_reminder', 'daily_reminder_time', 'daily_reminder_sent_on',
                'notify_streak_risk', 'streak_risk_sent_on',
            ]);
        });
    }
};
