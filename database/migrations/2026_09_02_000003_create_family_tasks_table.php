<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately its own table, never a column/relation on `tasks` — the
        // personal board's Task model is deeply wired into Projects/Groups/
        // Pomodoro-links/DayPlanner/streaks, and grafting multi-owner semantics
        // onto it would be both risky and unnecessary. A family task is also
        // shaped differently on purpose: ONE shared completion (like a chore),
        // not per-person completion the way Agenda homework needs — so unlike
        // agenda_entries there is no separate completions pivot here at all.
        Schema::create('family_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_space_id')->constrained()->cascadeOnDelete();

            // All three of these are nullOnDelete, not cascade: the task itself
            // must survive its creator's/assignee's/completer's account being
            // deleted, exactly like tasks.agenda_entry_id elsewhere in this app —
            // only the attribution is lost, never the task.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_tasks');
    }
};
