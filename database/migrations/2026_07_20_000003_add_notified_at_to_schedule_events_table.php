<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            // Stamped once the event-start push has been sent, so the
            // per-minute scan doesn't re-notify. Reset to null whenever
            // start_time changes.
            $table->timestamp('notified_at')->nullable()->after('pomodoro_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
