<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A private note on a shared entry is inherently per-viewer, so it lives in
     * its own table rather than a column on `agenda_entries` — the same reasoning
     * `agenda_entry_completions` already follows for "done". Unlike that table
     * this one carries a value, not just presence, so an empty/cleared note is a
     * deleted row rather than a NULL one (see AgendaEntry::setPrivateNoteFor()).
     */
    public function up(): void
    {
        Schema::create('agenda_entry_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes');

            $table->timestamps();

            // One private note per (entry, person) — writing again replaces it.
            $table->unique(['agenda_entry_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_entry_notes');
    }
};
