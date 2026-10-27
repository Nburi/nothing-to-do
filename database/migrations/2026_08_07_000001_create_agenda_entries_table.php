<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately standalone — no FK to tasks/projects/schedule_events.
        // The Agenda (homework & exams) stays isolated from the rest of the app for now.
        Schema::create('agenda_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // 'homework' | 'exam'
            $table->string('subject'); // Fach, free text
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('date'); // Abgabe-/Prüfungsdatum
            $table->boolean('is_done')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_entries');
    }
};
