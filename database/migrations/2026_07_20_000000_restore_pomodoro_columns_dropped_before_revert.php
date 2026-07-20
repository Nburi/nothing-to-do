<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A short-lived branch (merged, then reverted — see git history around
 * 2026-07-20) shipped a migration that dropped these columns and was never
 * rolled back before the revert deleted its file, so any environment that
 * migrated during that window is left permanently missing them (nothing in
 * the current migration set re-adds them). Every check here is a no-op on an
 * environment that never went through that window (local dev, or a fresh
 * install), so this is safe to run everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pomodoro_work')) {
                $table->unsignedSmallInteger('pomodoro_work')->default(25);
            }
            if (! Schema::hasColumn('users', 'pomodoro_short_break')) {
                $table->unsignedSmallInteger('pomodoro_short_break')->default(5);
            }
            if (! Schema::hasColumn('users', 'pomodoro_long_break')) {
                $table->unsignedSmallInteger('pomodoro_long_break')->default(15);
            }
            if (! Schema::hasColumn('users', 'pomodoro_long_every')) {
                $table->unsignedSmallInteger('pomodoro_long_every')->default(4);
            }
        });

        Schema::table('event_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('event_categories', 'pomodoro_enabled')) {
                $table->boolean('pomodoro_enabled')->default(false);
            }
        });

        Schema::table('schedule_events', function (Blueprint $table) {
            if (! Schema::hasColumn('schedule_events', 'pomodoro_started_at')) {
                $table->timestamp('pomodoro_started_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally a no-op: this migration only backfills columns the
        // current schema already expects elsewhere — nothing here should be
        // reversed independently of those.
    }
};
