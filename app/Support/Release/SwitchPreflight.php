<?php

namespace App\Support\Release;

use App\Support\Rehearsal\DatabaseSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 5.4: read-only checks to run on production (or a copy) before the switch, and again after it.
 * Reads only, never writes: no migration table, no repair, no cache. Works on the OLD schema as well
 * as on the migrated one, so every check first looks whether its table and columns exist; a check
 * whose columns are not there yet cannot find anything and counts as clean.
 *
 * Levels: BLOCK (data that would be lost or cannot be moved; the command exits non-zero),
 * WARN (needs a look or a decision by Niels, see docs/RELEASE_RUNBOOK.md), NOTE (what the switch will do).
 */
final class SwitchPreflight
{
    public const BLOCK = 'block';

    public const WARN = 'warn';

    public const NOTE = 'note';

    /** Category links of the old app that point at a project, a group or the Agenda (see droppedCategoryLinks). */
    public const DROPPED_TASK_SOURCES = ['project', 'group', 'agenda_entry', 'agenda_generic'];

    /** Orphan checks: [table, column, referenced table]. A foreign key may not point at nothing. */
    private const REFERENCES = [
        ['tasks', 'user_id', 'users'],
        ['tasks', 'area_id', 'areas'],
        ['tasks', 'project_id', 'projects'],
        ['tasks', 'group_id', 'task_groups'],
        ['tasks', 'stage_id', 'project_stages'],
        ['tasks', 'waits_on_id', 'tasks'],
        ['task_groups', 'user_id', 'users'],
        ['group_notes', 'task_group_id', 'task_groups'],
        ['projects', 'user_id', 'users'],
        ['project_stages', 'project_id', 'projects'],
        ['notes', 'user_id', 'users'],
        ['areas', 'user_id', 'users'],
        ['event_categories', 'user_id', 'users'],
        ['category_area', 'event_category_id', 'event_categories'],
        ['category_area', 'area_id', 'areas'],
        ['schedule_events', 'user_id', 'users'],
        ['schedule_events', 'category_id', 'event_categories'],
        ['craft_ideas', 'user_id', 'users'],
        ['reminders', 'user_id', 'users'],
    ];

    /** A task may only point at rows of its own user: [column, referenced table]. */
    private const OWNED_REFERENCES = [
        ['area_id', 'areas'],
        ['project_id', 'projects'],
        ['group_id', 'task_groups'],
    ];

    /**
     * @return array{counts: array<string, int>, checks: list<array{key: string, level: string, title: string, count: int, detail: string}>}
     */
    public function run(int $importantLimit = 10): array
    {
        $checks = [];

        foreach (self::REFERENCES as [$table, $column, $ref]) {
            $checks[] = $this->orphans($table, $column, $ref);
        }
        foreach (self::OWNED_REFERENCES as [$column, $ref]) {
            $checks[] = $this->foreignOwner($column, $ref);
        }

        $checks[] = $this->mixedGroups();
        $checks[] = $this->droppedCategoryLinks();
        $checks[] = $this->manyImportant($importantLimit);
        $checks[] = $this->uncopiedCraftIdeas();
        $checks[] = $this->oldProjectsList();
        $checks[] = $this->pendingMigrations();

        return ['counts' => $this->counts(), 'checks' => array_values(array_filter($checks))];
    }

    /** @param array{checks: list<array{level: string, count: int}>} $report */
    public static function blocks(array $report): bool
    {
        foreach ($report['checks'] as $check) {
            if ($check['level'] === self::BLOCK && $check['count'] > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, int> row count per table (volatile ones such as sessions and cache left out) */
    private function counts(): array
    {
        $names = array_map(fn (array $t) => $t['name'], Schema::getTables());
        sort($names);

        $counts = [];
        foreach ($names as $table) {
            if (! in_array($table, DatabaseSnapshot::VOLATILE_TABLES, true)) {
                $counts[$table] = DB::table($table)->count();
            }
        }

        return $counts;
    }

    /** Rows whose foreign key points at a row that does not exist (possible where the database enforces no foreign keys, e.g. SQLite). */
    private function orphans(string $table, string $column, string $ref): ?array
    {
        if (! $this->has($table, $column) || ! $this->has($ref)) {
            return null;
        }

        $query = DB::table($table)
            ->leftJoin("{$ref} as ref", "{$table}.{$column}", '=', 'ref.id')
            ->whereNotNull("{$table}.{$column}")
            ->whereNull('ref.id');

        return $this->check("orphan.{$table}.{$column}", self::BLOCK, "{$table}.{$column} points at a {$ref} row that does not exist",
            (clone $query)->count(), $this->sampleIds(clone $query, $table));
    }

    /** A task that points at a project, group or area that belongs to another user. */
    private function foreignOwner(string $column, string $ref): ?array
    {
        if (! $this->has('tasks', $column) || ! $this->has($ref, 'user_id')) {
            return null;
        }

        $query = DB::table('tasks')
            ->join("{$ref} as ref", "tasks.{$column}", '=', 'ref.id')
            ->whereColumn('ref.user_id', '!=', 'tasks.user_id');

        return $this->check("foreign.tasks.{$column}", self::BLOCK, "tasks.{$column} points at a row of another user in {$ref}",
            (clone $query)->count(), $this->sampleIds(clone $query, 'tasks'));
    }

    /** Groups whose open tasks sit in more than one list: the 4.3 migration turns each into a project (C32). */
    private function mixedGroups(): ?array
    {
        if (! $this->has('tasks', 'group_id') || ! $this->has('task_groups')) {
            return null;
        }

        $groups = DB::table('task_groups')
            ->when($this->has('task_groups', 'project_id'), fn ($q) => $q->whereNull('project_id'))
            ->select('id');

        $mixed = DB::table('tasks')
            ->whereIn('group_id', $groups)
            ->whereNull('project_id')
            ->where('is_completed', false)
            ->groupBy('group_id')
            ->havingRaw('count(distinct list) > 1')
            ->pluck('group_id');

        return $this->check('groups.mixed', self::NOTE, 'groups with open tasks in several lists (each becomes a project at migrate)',
            $mixed->count(), $this->ids($mixed->all()));
    }

    /**
     * Categories linked to a project, group or Agenda entry. Decided (5.4, "catlinks"): project and group links become
     * "suggest its next steps in this block" and stay as they are; the Agenda ones become a text link at migrate.
     */
    private function droppedCategoryLinks(): ?array
    {
        if (! $this->has('event_categories', 'task_source')) {
            return null;
        }

        $query = DB::table('event_categories')
            ->whereIn('task_source', self::DROPPED_TASK_SOURCES)
            ->when($this->has('event_categories', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'));

        return $this->check('categories.dropped_links', self::NOTE, 'categories linked to a project, group or Agenda entry (project and group suggest their next steps, Agenda becomes a text link)',
            (clone $query)->count(), $this->sampleIds(clone $query, 'event_categories'));
    }

    /** Users with many open "Wichtig" marks (Eisenhower board users): the new board ranks them first. */
    private function manyImportant(int $limit): ?array
    {
        if (! $this->has('tasks', 'is_important')) {
            return null;
        }

        $users = DB::table('tasks')
            ->where('is_important', true)
            ->where('is_completed', false)
            ->groupBy('user_id')
            ->havingRaw('count(*) > ?', [$limit])
            ->selectRaw('user_id, count(*) as marked')
            ->orderByDesc('marked')
            ->get();

        $detail = $users->take(10)->map(fn ($row) => "user {$row->user_id}: {$row->marked}")->implode(', ');

        return $this->check('users.many_important', self::WARN, "users with more than {$limit} open tasks marked as Wichtig (decision pending)",
            $users->count(), $detail);
    }

    private function uncopiedCraftIdeas(): ?array
    {
        if (! $this->has('craft_ideas')) {
            return null;
        }

        $count = DB::table('craft_ideas')
            ->when($this->has('craft_idea_copies'), fn ($q) => $q->whereNotExists(
                fn ($sub) => $sub->from('craft_idea_copies')->whereColumn('craft_idea_copies.craft_idea_id', 'craft_ideas.id')
            ))
            ->count();

        return $this->check('craft_ideas.uncopied', self::NOTE, 'craft ideas not copied into Later yet (php artisan app:copy-craft-ideas)', $count, '');
    }

    /** Tasks in the retired "projects" list: migration 2026_10_31_000001 moves them to "tasks". */
    private function oldProjectsList(): ?array
    {
        if (! $this->has('tasks', 'list')) {
            return null;
        }

        return $this->check('tasks.old_projects_list', self::NOTE, 'tasks in the retired list "projects" (moved to "tasks" at migrate)',
            DB::table('tasks')->where('list', 'projects')->count(), '');
    }

    private function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $files = array_keys($migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths())));
        $ran = $this->has('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];

        return $this->check('migrations.pending', self::NOTE, 'migrations not run yet (php artisan migrate)', count(array_diff($files, $ran)), '');
    }

    private function has(string $table, ?string $column = null): bool
    {
        return $column === null ? Schema::hasTable($table) : (Schema::hasTable($table) && Schema::hasColumn($table, $column));
    }

    /** @return array{key: string, level: string, title: string, count: int, detail: string} */
    private function check(string $key, string $level, string $title, int $count, string $detail): array
    {
        return ['key' => $key, 'level' => $level, 'title' => $title, 'count' => $count, 'detail' => $count > 0 ? $detail : ''];
    }

    private function sampleIds($query, string $table): string
    {
        return $this->has($table, 'id') ? $this->ids($query->limit(6)->pluck("{$table}.id")->all()) : '';
    }

    /** @param list<int|string> $ids */
    private function ids(array $ids): string
    {
        return $ids === [] ? '' : 'ids '.implode(', ', array_slice($ids, 0, 5)).(count($ids) > 5 ? ', …' : '');
    }
}
