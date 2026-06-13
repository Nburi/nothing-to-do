<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');

            // 'inbox' | 'todos' | 'tasks'. Kept as a string (not a DB enum) so a
            // future 'projects' value — and a nullable project_id FK — drop in
            // with a plain ALTER TABLE, no schema rewrite.
            $table->string('list')->default('inbox');

            $table->boolean('is_today')->default(false);
            $table->boolean('is_important')->default(false);

            // Hard deadline (external, authoritative) vs soft due date (self-set).
            $table->date('deadline')->nullable();
            $table->date('due_date')->nullable();

            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();

            // Manual ordering within a list (drag & drop).
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Board reads always scope by user + list + completion.
            $table->index(['user_id', 'list', 'is_completed']);
            $table->index(['user_id', 'is_today']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
