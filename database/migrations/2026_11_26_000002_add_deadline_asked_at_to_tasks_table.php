<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step d-bis-wann: marking an undated task „Wichtig" offers a quiet „Bis wann?" once. This column
 * remembers that the question was shown, so the same task is never asked again, not after it was
 * ignored, answered, unmarked or marked again, and not from another device.
 *
 * Additive only: nullable, no default, nothing is read from or dropped out of another column.
 * Null on every existing row, which is right: nobody was asked yet. No data is migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('deadline_asked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('deadline_asked_at');
        });
    }
};
