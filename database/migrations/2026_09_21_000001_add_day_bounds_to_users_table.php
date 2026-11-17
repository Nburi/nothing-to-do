<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The user's default "Tagesrahmen" — when their day starts and ends on the
 * timeline. Until now this was hardcoded as 06:00–23:00 in three separate
 * Livewire components (Schedule, WeekPlan, PrepareTomorrow).
 *
 * `weekday_day_bounds` holds per-ISO-weekday overrides as
 * {"6":{"start":"08:00","end":"23:30"}} — nullable, and null means "no
 * overrides at all", the same "untouched means default" shape as
 * users.header_badges and users.hidden_modules. A concrete single date is
 * overridden through its own table instead (schedule_day_bounds), mirroring
 * how SchedulePause already stores one row per date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('day_start_time', 5)->default('06:00')->after('task_reset_time');
            $table->string('day_end_time', 5)->default('23:00')->after('day_start_time');
            $table->json('weekday_day_bounds')->nullable()->after('day_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['day_start_time', 'day_end_time', 'weekday_day_bounds']);
        });
    }
};
