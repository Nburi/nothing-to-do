<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 4.3 (Niels, 2026-11-17): a group holds only one list now (To-dos or Tasks, …). A group
 * whose open tasks sit in more than one list becomes a project instead. Nothing moves and nothing
 * is deleted: the tasks keep their list and group_id and gain project_id (unless they already
 * had one), the group row and its note cards stay, the note cards are copied into the project's
 * notes. Every group that stays a group gets its one list in `list` (the list of its open tasks;
 * a finished group takes the list of its newest task). Idempotent (converted groups carry
 * project_id, filled lists are not touched) and reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('task_groups')->whereNull('project_id')->orderBy('id')->get();

        foreach ($groups as $group) {
            $lists = DB::table('tasks')
                ->where('group_id', $group->id)->whereNull('project_id')->where('is_completed', false)
                ->distinct()->pluck('list');

            if ($lists->count() < 2) {
                if ($group->list === null) {
                    $list = $lists->first() ?? DB::table('tasks')->where('group_id', $group->id)->orderByDesc('id')->value('list') ?? 'tasks';
                    DB::table('task_groups')->where('id', $group->id)->update(['list' => $list]);
                }

                continue;
            }

            DB::transaction(function () use ($group) {
                $notes = DB::table('group_notes')->where('task_group_id', $group->id)
                    ->orderBy('sort_order')->orderBy('id')->pluck('content')
                    ->map(fn ($n) => trim((string) $n))->filter()->implode("\n\n");

                $projectId = DB::table('projects')->insertGetId([
                    'user_id' => $group->user_id,
                    'name' => $group->name,
                    'brainstorm' => $notes === '' ? null : $notes,
                    'sort_order' => (int) DB::table('projects')->where('user_id', $group->user_id)->max('sort_order') + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('tasks')->where('group_id', $group->id)->whereNull('project_id')->update(['project_id' => $projectId]);
                DB::table('task_groups')->where('id', $group->id)->update(['project_id' => $projectId]);
            });
        }
    }

    public function down(): void
    {
        foreach (DB::table('task_groups')->whereNotNull('project_id')->get() as $group) {
            DB::transaction(function () use ($group) {
                DB::table('tasks')->where('group_id', $group->id)->where('project_id', $group->project_id)->update(['project_id' => null]);
                DB::table('task_groups')->where('id', $group->id)->update(['project_id' => null]);
                DB::table('projects')->where('id', $group->project_id)->delete();
            });
        }
    }
};
