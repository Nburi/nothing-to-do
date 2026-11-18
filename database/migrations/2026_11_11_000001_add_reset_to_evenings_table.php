<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 3.2: the ten minute reset and the battery question at its end live on
 * tonight's Evening row (R2, G4), so a reload or a second device shows the same thing.
 *
 * - reset_started_at: "Los" was tapped (or the reset was started by hand).
 * - reset_closed_at: the reset was skipped or ended early; a reset that ran its ten minutes
 *   is closed by time alone, nothing has to write.
 * - battery_asked_at: the battery question was answered or put off with "Später" tonight.
 *
 * Additive and nullable: existing rows mean "no reset tonight", nothing to migrate. A row
 * whose battery is already set counts as asked (see Evening::batteryAsked()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evenings', function (Blueprint $table) {
            $table->timestamp('reset_started_at')->nullable()->after('skipped_task_ids');
            $table->timestamp('reset_closed_at')->nullable()->after('reset_started_at');
            $table->timestamp('battery_asked_at')->nullable()->after('reset_closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('evenings', function (Blueprint $table) {
            $table->dropColumn(['reset_started_at', 'reset_closed_at', 'battery_asked_at']);
        });
    }
};
