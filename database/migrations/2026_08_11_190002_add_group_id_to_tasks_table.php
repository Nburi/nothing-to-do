<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A task's group is orthogonal to its list (exactly like `is_today`): a
     * grouped task still lives in inbox/todos/tasks, it just surfaces inside
     * the group instead of loose on the board. Dissolving a group therefore
     * only has to null this column — nullOnDelete does that for free, so a
     * deleted group never takes its tasks with it.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('project_id')
                ->constrained('task_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
