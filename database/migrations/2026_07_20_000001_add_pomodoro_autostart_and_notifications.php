<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Whether a phase transition after the first (always-manual) work
            // session continues on its own, or waits for a manual tap.
            $table->boolean('pomodoro_autostart')->default(false)->after('pomodoro_long_every');

            // Which moments should raise a browser notification.
            $table->boolean('notify_event_start')->default(false)->after('pomodoro_autostart');
            $table->boolean('notify_pomo_start')->default(false)->after('notify_event_start');
            $table->boolean('notify_break_start')->default(false)->after('notify_pomo_start');
        });

        Schema::table('schedule_events', function (Blueprint $table) {
            // The Pomodoro phase/cycle this event is currently on (or was on,
            // if pomodoro_started_at is null because it's frozen awaiting a
            // manual continue). Null = never started.
            $table->string('pomodoro_phase', 20)->nullable()->after('pomodoro_started_at');
            $table->unsignedSmallInteger('pomodoro_cycle')->default(1)->after('pomodoro_phase');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn(['pomodoro_phase', 'pomodoro_cycle']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pomodoro_autostart', 'notify_event_start', 'notify_pomo_start', 'notify_break_start',
            ]);
        });
    }
};
