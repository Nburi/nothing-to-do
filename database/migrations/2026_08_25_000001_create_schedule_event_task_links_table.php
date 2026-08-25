<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces schedule_events.linked_task_id (one task per entry) with a
     * pivot, so an entry can bind several — same shape as category_task_links
     * for a category's own "Bestimmte Aufgaben" source. Cascades both ways: a
     * pin has no meaning outside the entry that made it.
     */
    public function up(): void
    {
        Schema::create('schedule_event_task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['schedule_event_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_event_task_links');
    }
};
