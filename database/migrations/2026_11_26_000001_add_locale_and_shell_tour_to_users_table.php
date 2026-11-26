<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 5.1: the language and the first-run tour are per-user settings.
 *
 * Additive only, nothing is read from or dropped out of another column:
 * - `locale`: German by default, so every existing user keeps the language they have.
 * - `shell_tour_seen_at`: null means "never saw the three-tap tour of the new app". Everyone who
 *   exists when this migration runs is stamped as having seen it, so no existing user is pushed
 *   through it unexpectedly. Only accounts created afterwards start with null. The switch (5.4)
 *   can null the column again if everybody should get the tour once.
 *   (`onboarding_completed_at` cannot serve here: it is null for every account that predates the
 *   old tutorial, and it belongs to the old tutorial.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('de');
            $table->timestamp('shell_tour_seen_at')->nullable();
        });

        DB::table('users')->update(['shell_tour_seen_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'shell_tour_seen_at']);
        });
    }
};
