<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per concrete date whose day frame differs from the user's default
 * (or from that weekday's own override) — "heute schlafe ich aus". Same
 * one-row-per-date shape as schedule_pauses, and for the same reason: a
 * single day has to be adjustable independently of any range around it.
 *
 * The unique index gets an explicit short name: the auto-generated
 * `schedule_day_bounds_user_id_date_unique` is fine at 39 characters, but
 * naming it here keeps this table consistent with the rest of the schema and
 * removes any doubt about MariaDB's 64-character identifier cap (see
 * CLAUDE.md §10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_day_bounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->timestamps();

            $table->unique(['user_id', 'date'], 'sdb_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_day_bounds');
    }
};
