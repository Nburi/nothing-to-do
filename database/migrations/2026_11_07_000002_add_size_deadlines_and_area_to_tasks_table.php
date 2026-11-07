<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor step 1.1, first half of the two-step renames (REFACTOR_PLAN § 3):
 *
 *   tasks.deadline  → tasks.hard_deadline
 *   tasks.due_date  → tasks.soft_deadline ("finished by")
 *
 * This release ADDS the new columns and copies the data. The old columns stay
 * and are kept in sync by Task::booted() for as long as both exist, so code
 * that still reads `deadline`/`due_date` keeps working while reads move over.
 * Dropping the old columns is a later release (TODO.md).
 *
 * Also adds `size` (G1: quick | short | long | block). It stays NULL for every
 * existing row on purpose: NULL means "not sized yet", which the daily sort
 * needs to tell apart from a deliberate Short; the engine already reads NULL as
 * Short (App\Planning\Size::fromNullable). And `area_id` (step 1.6), which
 * nulls out when an area is deleted so a task is never lost with its area.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('size', 16)->nullable()->after('list');
            $table->date('hard_deadline')->nullable()->after('deadline');
            $table->date('soft_deadline')->nullable()->after('due_date');
            $table->foreignId('area_id')->nullable()->after('user_id')
                ->constrained('areas')->nullOnDelete();
        });

        // Query builder on purpose: no model events, no updated_at bump.
        DB::table('tasks')->whereNotNull('deadline')->update(['hard_deadline' => DB::raw('deadline')]);
        DB::table('tasks')->whereNotNull('due_date')->update(['soft_deadline' => DB::raw('due_date')]);
    }

    public function down(): void
    {
        // Safe: the old columns still hold the same data (kept in sync).
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['size', 'hard_deadline', 'soft_deadline']);
        });
    }
};
