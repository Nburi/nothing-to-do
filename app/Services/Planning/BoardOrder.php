<?php

namespace App\Services\Planning;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Step 4.8 (C31, Niels 2026-11-24, variant A): one slot per card in one shared order of tasks,
 * projects and groups. `board_position` is one number space per user across the three tables;
 * "further up" means "tends to be more important" and is the last thing the pick engine looks at.
 *
 * A reference is "task:12", "project:3" or "group:4". The board sends the new order of one
 * container (a column or the „Wichtig" band); the server hands the positions those entries already
 * held to them in the new order. So a drop never touches an entry that was not part of it, and the
 * other columns keep their order.
 */
final class BoardOrder
{
    /** @var array<string, class-string<Model>> */
    private const MODELS = ['task' => Task::class, 'project' => Project::class, 'group' => TaskGroup::class];

    public static function ref(Model $model): string
    {
        return match (true) {
            $model instanceof Task => 'task:'.$model->id,
            $model instanceof Project => 'project:'.$model->id,
            default => 'group:'.$model->id,
        };
    }

    /** Where a new row goes: below everything the user has. */
    public static function nextPosition(int $userId): int
    {
        return self::extreme($userId, 'max') + 1;
    }

    /** Above everything the user has. */
    public static function topPosition(int $userId): int
    {
        return self::extreme($userId, 'min') - 1;
    }

    private static function extreme(int $userId, string $fn): int
    {
        $values = [];
        foreach (['tasks', 'projects', 'task_groups'] as $table) {
            $values[] = DB::table($table)->where('user_id', $userId)->{$fn}('board_position');
        }
        $values = array_filter($values, fn ($v) => $v !== null);

        return $values === [] ? 0 : (int) ($fn === 'max' ? max($values) : min($values));
    }

    /**
     * Put the entries in this order. Unknown or foreign references are skipped (never trusted);
     * the rest swap the positions they already hold.
     *
     * @param  list<string>  $refs  new order of one container, first = top
     * @return int how many entries changed their position
     */
    public function place(User $user, array $refs): int
    {
        $entries = $this->load($user, $refs);
        if (count($entries) < 2) {
            return 0;
        }

        return DB::transaction(function () use ($user, $entries) {
            // Entries that never had a position get one below everything, in the order they are given.
            $next = self::nextPosition($user->id);
            $positions = array_map(fn (Model $m) => $m->board_position ?? $next++, $entries);
            sort($positions);

            $changed = 0;
            foreach ($entries as $i => $model) {
                if ($model->board_position !== $positions[$i]) {
                    $model->newQuery()->whereKey($model->getKey())->update(['board_position' => $positions[$i]]);
                    $changed++;
                }
            }

            return $changed;
        });
    }

    /**
     * The same as place(), for callers that only know task ids: the old board's drops, the REST
     * reorder and MCP `set_task_order`. They keep writing `sort_order` (the old screens read it) and
     * call this so the new board follows. Only open loose tasks have a place of their own; steps of a
     * project or group sit where their card sits, and finished tasks have none.
     *
     * @param  array<int, int|string>  $ids  task ids in the new order, first = top
     * @return int how many tasks changed their position
     */
    public function placeTasks(User $user, array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $loose = Task::forUser($user)->active()->whereNull('project_id')->whereNull('group_id')
            ->whereKey($ids)->pluck('id')->all();

        return $this->place($user, array_map(
            fn (int $id) => 'task:'.$id,
            array_values(array_filter($ids, fn (int $id) => in_array($id, $loose, true))),
        ));
    }

    /** "Nach oben holen": the entry becomes the first of the whole board. */
    public function toTop(User $user, string $ref): bool
    {
        $model = $this->load($user, [$ref])[0] ?? null;
        if ($model === null) {
            return false;
        }

        $model->newQuery()->whereKey($model->getKey())->update(['board_position' => self::topPosition($user->id)]);

        return true;
    }

    /**
     * @param  list<string>  $refs
     * @return list<Model> in the order of $refs, each once
     */
    private function load(User $user, array $refs): array
    {
        $wanted = [];
        foreach ($refs as $ref) {
            if (! is_string($ref) || ! preg_match('/^(task|project|group):(\d+)$/', $ref, $m)) {
                continue;
            }
            $wanted[$m[1]][(int) $m[2]] = true;
        }

        $found = [];
        foreach ($wanted as $kind => $ids) {
            $class = self::MODELS[$kind];
            $query = $class::query()->forUser($user)->whereKey(array_keys($ids));
            if ($kind === 'task') {
                $query->active();
            }
            foreach ($query->get() as $model) {
                $found[$kind.':'.$model->id] = $model;
            }
        }

        $out = [];
        foreach ($refs as $ref) {
            if (is_string($ref) && isset($found[$ref]) && ! in_array($found[$ref], $out, true)) {
                $out[] = $found[$ref];
            }
        }

        return $out;
    }
}
