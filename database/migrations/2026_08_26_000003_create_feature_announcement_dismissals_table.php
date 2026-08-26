<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (announcement, person) who has dismissed it — mirrors
     * agenda_entry_completions exactly (CLAUDE.md, Agenda — Klassen teilen):
     * "seen" is a property of the pair, not the announcement, since every user
     * dismisses their own copy of the feed independently.
     */
    public function up(): void
    {
        Schema::create('feature_announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Dismissing twice must be a no-op, not a second row.
            $table->unique(['feature_announcement_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_announcement_dismissals');
    }
};
