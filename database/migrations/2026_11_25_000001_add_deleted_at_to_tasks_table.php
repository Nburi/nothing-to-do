<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.9: deleting a single item on the board is undoable ("Rückgängig", CONVENTIONS 7), so a
 * task is soft deleted. A hard delete would cascade away its day plans and work sessions, and
 * none of that could be brought back (same reasoning as event_categories, step 4.1).
 *
 * Additive and nullable: every existing task stays as it is. down() only drops the column
 * (soft-deleted tasks would then show again, nothing is lost).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
