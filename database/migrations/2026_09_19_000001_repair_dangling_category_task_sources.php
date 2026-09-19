<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A category's linked project/group/Agenda entry could be deleted, which nulls the FK
 * (nullOnDelete) but used to leave `task_source` set to a source with nothing behind it.
 * Repairs those rows; AppServiceProvider now resets `task_source` on delete going forward.
 * Purely cosmetic and safe: a dangling source already behaved as "no link".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'project' => 'linked_project_id',
            'group' => 'linked_group_id',
            'agenda_entry' => 'linked_agenda_entry_id',
        ] as $source => $column) {
            DB::table('event_categories')
                ->where('task_source', $source)
                ->whereNull($column)
                ->update(['task_source' => null]);
        }
    }

    public function down(): void
    {
        // Not reversible, and nothing to restore: the source had no target.
    }
};
