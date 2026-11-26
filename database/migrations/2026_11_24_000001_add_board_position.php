<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4.8 (C31): one shared order for everything on the board. `board_position` sits on tasks,
 * projects and task groups and is one number space per user: lower = further up.
 *
 * Purely additive: no existing column is touched or removed, `sort_order` keeps its meaning (the
 * order of steps inside a project or group, the old app's ordering). The backfill gives every open
 * loose task, project and group a position that reproduces what the board showed before: projects,
 * then groups, then loose tasks by their old order. Nobody sees a change until they drag something.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'projects', 'task_groups'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->integer('board_position')->nullable()->index();
            });
        }

        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            $position = 0;

            foreach (DB::table('projects')->where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->pluck('id') as $id) {
                DB::table('projects')->where('id', $id)->update(['board_position' => ++$position]);
            }

            foreach (DB::table('task_groups')->where('user_id', $userId)->orderBy('sort_order')->orderBy('id')->pluck('id') as $id) {
                DB::table('task_groups')->where('id', $id)->update(['board_position' => ++$position]);
            }

            // Loose tasks as the board listed them: marked ones were on top of their list, then the user's order.
            $tasks = DB::table('tasks')->where('user_id', $userId)
                ->where('is_completed', false)->whereNull('project_id')->whereNull('group_id')
                ->orderByDesc('is_important')->orderBy('sort_order')->orderBy('id')
                ->pluck('id');

            foreach ($tasks as $id) {
                DB::table('tasks')->where('id', $id)->update(['board_position' => ++$position]);
            }
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'projects', 'task_groups'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex(['board_position']);
                $t->dropColumn('board_position');
            });
        }
    }
};
