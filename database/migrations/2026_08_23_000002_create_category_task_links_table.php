<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs a category's task_source = 'tasks' state: a handful of individually
     * pinned tasks (rather than a whole project/group) suggested during that
     * category's focus sessions, in a chosen order. Cascades both ways —
     * deleting the category or the task drops the pin, there is nothing left
     * to keep.
     */
    public function up(): void
    {
        Schema::create('category_task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('event_categories')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_task_links');
    }
};
