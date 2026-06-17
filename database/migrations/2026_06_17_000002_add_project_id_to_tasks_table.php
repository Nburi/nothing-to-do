<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // A task may belong to a project. When it does, its `list` is
            // 'projects' and it lives on the project page, not the main board.
            // Deleting a project releases its tasks (handled in the component:
            // they fall back to the inbox), so null-on-delete is the safety net.
            $table->foreignId('project_id')
                ->nullable()
                ->after('list')
                ->constrained()
                ->nullOnDelete();

            $table->index(['user_id', 'project_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['user_id', 'project_id', 'is_completed']);
            $table->dropColumn('project_id');
        });
    }
};
