<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // When the Brief is nudged: 'evening' (plan tomorrow) or 'morning' (plan today).
            $table->string('brief_when', 10)->default('evening')->after('task_reset_time');
            // The time of day the dashboard nudge appears (HH:MM).
            $table->string('brief_time', 5)->default('19:00')->after('brief_when');
            // The day the nudge was last dismissed, so it stays quiet until the next.
            $table->date('brief_dismissed_on')->nullable()->after('brief_time');

            // Pomodoro rhythm (minutes / count) used to generate Work-Sessions.
            $table->unsignedSmallInteger('pomodoro_work')->default(25)->after('brief_dismissed_on');
            $table->unsignedSmallInteger('pomodoro_short_break')->default(5)->after('pomodoro_work');
            $table->unsignedSmallInteger('pomodoro_long_break')->default(15)->after('pomodoro_short_break');
            $table->unsignedSmallInteger('pomodoro_long_every')->default(4)->after('pomodoro_long_break');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'brief_when', 'brief_time', 'brief_dismissed_on',
                'pomodoro_work', 'pomodoro_short_break', 'pomodoro_long_break', 'pomodoro_long_every',
            ]);
        });
    }
};
