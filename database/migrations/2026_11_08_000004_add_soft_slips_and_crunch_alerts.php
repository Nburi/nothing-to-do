<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 1.4 (R5; risks F5, F7):
 *
 * - `tasks.soft_slips`: how often the soft deadline was missed (G2). 0 for
 *   every existing row; the slip rule counts from the day it first runs.
 * - `crunch_alerts`: one row per user with the early warning's state, which
 *   the hysteresis needs (on at the first missing minute, off only with a
 *   session of room to spare) and the "at most one warning a day" rule.
 *
 * Additive only. down() drops just what this adds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedTinyInteger('soft_slips')->default(0)->after('soft_deadline');
        });

        Schema::create('crunch_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(false);
            $table->string('level', 8)->nullable();
            $table->date('crunch_date')->nullable();
            $table->unsignedInteger('missing_minutes')->default(0);
            $table->date('raised_on')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crunch_alerts');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('soft_slips');
        });
    }
};
