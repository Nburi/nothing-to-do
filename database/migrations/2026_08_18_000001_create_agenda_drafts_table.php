<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who is actively creating or editing an entry in a shared class right now —
     * a presence-shaped, TTL-read signal (see AgendaDraft::TTL_SECONDS), not a
     * durable record. One row per user: a person can only have one form open at
     * a time, and a second tab overwriting the first is the same simplification
     * `users.last_seen_at` already makes.
     */
    public function up(): void
    {
        Schema::create('agenda_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('agenda_space_id')->constrained()->cascadeOnDelete();
            // Null while creating a new entry; set while editing an existing one.
            $table->foreignId('agenda_entry_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('subject')->default('');

            $table->timestamps();

            $table->index('agenda_space_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_drafts');
    }
};
