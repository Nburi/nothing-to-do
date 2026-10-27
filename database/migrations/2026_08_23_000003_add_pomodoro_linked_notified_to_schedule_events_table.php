<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks whether the "linked list just finished" notice (see
     * TaskBoard::linkedSourceNotice()) has already been shown for the
     * *current* Pomodoro session on this event — reset to false whenever a
     * fresh session starts (PomodoroSessionService::start()/stop()), so the
     * quiet one-time notice can fire again on a later session without
     * re-appearing on every 5s poll of the same session.
     */
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->boolean('pomodoro_linked_notified')->default(false)->after('pomodoro_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn('pomodoro_linked_notified');
        });
    }
};
