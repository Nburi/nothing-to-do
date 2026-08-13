<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces task_groups.notes (a single Markdown blob) with several
     * separate note cards per group — a group can hold a few unrelated things
     * worth jotting down, and one growing document made finding any single one
     * of them slower. Cascades on group delete: unlike a task, a note has no
     * meaning outside the group it was written for, so there is nothing to
     * release it back to.
     */
    public function up(): void
    {
        Schema::create('group_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_group_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['task_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_notes');
    }
};
