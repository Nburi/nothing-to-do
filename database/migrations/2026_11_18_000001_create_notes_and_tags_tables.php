<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.3b: note cards for projects and groups, and the user's shared tags.
 *
 * A note belongs to a project or a group (exactly one, enforced by App\Services\Projects\NoteEditor)
 * and, in a project, optionally to a stage (null = "Lose"). Removing a stage leaves its notes loose.
 * `legacy_source` marks the cards 2026_11_18_000002 copied from old data, so that copy is
 * idempotent and its rollback removes nothing a person wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('task_groups')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('project_stages')->nullOnDelete();
            $table->text('body');
            $table->integer('sort_order')->default(0);
            $table->string('legacy_source', 40)->nullable()->unique();
            $table->timestamps();

            $table->index(['project_id', 'stage_id', 'sort_order']);
            $table->index(['group_id', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('note_tag', function (Blueprint $table) {
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['note_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('notes');
    }
};
