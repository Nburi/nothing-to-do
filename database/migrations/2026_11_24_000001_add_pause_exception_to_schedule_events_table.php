<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.1 (B6, Niels 2026-11-24): on a paused day the blocks it would have had show as pale ghosts,
 * and one ghost can be brought back for that day. A restored ghost is the occurrence's own row (the
 * "this week only" row of its series) marked as an exception to the pause, so the pause keeps hiding
 * the other blocks, switching the day back on later shows it once (never twice), and removing the
 * exception puts the ghost back.
 *
 * Additive: a boolean with default false. Every existing row keeps false, so nothing changes for
 * existing data; the old screens and the engine behave as before for rows without the mark.
 * down() only drops the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->boolean('pause_exception')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('schedule_events', function (Blueprint $table) {
            $table->dropColumn('pause_exception');
        });
    }
};
