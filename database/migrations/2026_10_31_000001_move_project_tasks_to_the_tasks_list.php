<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Projects, task groups and the emergency mode were removed from the app.
 * Tasks that lived in the old "projects" list would otherwise disappear from
 * every board (no column shows that list any more), so they move into the
 * regular "tasks" list. Nothing is deleted: project_id / group_id /
 * emergency_list and the projects / task_groups tables stay as they are, so
 * the data can still be recovered or migrated to a future feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tasks')->where('list', 'projects')->update(['list' => 'tasks']);
    }

    public function down(): void
    {
        // Not reversible: the original list of the moved tasks is not kept
        // separately — project_id still marks which of them belonged to one.
    }
};
