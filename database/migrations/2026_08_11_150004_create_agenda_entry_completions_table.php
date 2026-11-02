<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Done" stops being a property of the entry and becomes a property of the
     * (entry, person) pair: a class entry is ticked off by 22 people
     * independently, and nobody may clear it for anyone else.
     *
     * Private entries go through the same table rather than keeping their own
     * mechanism — one row per person who finished it, where a private entry
     * simply has exactly one person who can. That keeps "what's still open for
     * me" a single NOT EXISTS query instead of a branch over two mechanisms.
     *
     * `agenda_entries.is_done` is deliberately LEFT IN PLACE by this migration
     * (see CLAUDE.md §8 — data-losing changes ship in two steps). It is backfilled
     * into this table here and stops being read; a later commit drops the column
     * once this has run in production. See TODO.md.
     */
    public function up(): void
    {
        Schema::create('agenda_entry_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Ticking twice must be a no-op, not a second row.
            $table->unique(['agenda_entry_id', 'user_id']);
        });

        // Backfill: every entry already marked done belongs to exactly one user,
        // so its completion is that user's. Chunked — this runs against whatever
        // history production has.
        DB::table('agenda_entries')
            ->where('is_done', true)
            ->select('id', 'user_id')
            ->orderBy('id')
            ->chunk(500, function ($entries) {
                $now = now();

                DB::table('agenda_entry_completions')->insert(
                    $entries->map(fn ($entry) => [
                        'agenda_entry_id' => $entry->id,
                        'user_id' => $entry->user_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        // `is_done` was never cleared, so rolling back loses nothing.
        Schema::dropIfExists('agenda_entry_completions');
    }
};
