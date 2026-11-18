<?php

namespace App\Services\Planning;

use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Step 4.1: "Rückgängig" for every week plan edit (CONVENTIONS 7: deletes are undoable, not confirmed).
 *
 * record() runs one editor operation and writes down, row by row, what it changed: rows it created,
 * the old values of rows it updated, and the full content of rows it deleted, including the
 * occurrence's attribute values and linked tasks (both cascade on delete). undo() plays that back
 * in reverse, so an undo restores exactly what was there, also rows "every week" removed on purpose.
 *
 * It listens to the model events of ScheduleEvent and EventTemplate, so it only sees changes made
 * through models; WeekPlanEditor uses models for everything it changes. Pauses are written in bulk
 * and noted explicitly (notePaused / noteResumed).
 *
 * The journal is plain data (raw column values). The editor page keeps it in a locked Livewire
 * property; undo() still scopes every row to the user, so a journal can never touch another account.
 */
final class WeekPlanJournal
{
    private static ?self $active = null;

    /** @var list<array<string, mixed>> */
    private array $entries = [];

    /** Called once per application boot (AppServiceProvider); each boot has its own event dispatcher. */
    public static function boot(): void
    {
        foreach ([ScheduleEvent::class => 'event', EventTemplate::class => 'template'] as $class => $type) {
            $class::created(fn (Model $m) => self::$active?->add('created', $type, $m));
            $class::updating(fn (Model $m) => self::$active?->add('updated', $type, $m));
            $class::deleting(fn (Model $m) => self::$active?->add('deleted', $type, $m));
        }
    }

    /**
     * Run $op and return [its result, the journal to undo it]. A nested call records into the
     * outer journal (one undo for the whole edit).
     *
     * @return array{0: mixed, 1: list<array<string, mixed>>}
     */
    public static function record(Closure $op): array
    {
        if (self::$active !== null) {
            return [$op(), []];
        }

        self::$active = new self;
        try {
            $result = $op();

            return [$result, self::$active->entries];
        } finally {
            self::$active = null;
        }
    }

    /** @param  list<string>  $dates  dates that were not paused before */
    public static function notePaused(array $dates): void
    {
        if ($dates !== []) {
            self::$active?->push(['kind' => 'paused', 'dates' => array_values($dates)]);
        }
    }

    /** @param  list<array{date: string, note: ?string}>  $rows  the pauses that were switched off */
    public static function noteResumed(array $rows): void
    {
        if ($rows !== []) {
            self::$active?->push(['kind' => 'resumed', 'rows' => array_values($rows)]);
        }
    }

    /** @param  list<array<string, mixed>>  $entries */
    public static function undo(User $user, array $entries): void
    {
        DB::transaction(function () use ($user, $entries) {
            foreach (array_reverse($entries) as $entry) {
                match ($entry['kind'] ?? null) {
                    'created' => self::query($entry['type'], $user)->whereKey($entry['id'])->first()?->delete(),
                    'updated' => self::table($entry['type'])->where('user_id', $user->id)->where('id', $entry['id'])->update($entry['before']),
                    'deleted' => self::reinsert($user, $entry),
                    'paused' => SchedulePause::forUser($user)->whereIn(DB::raw('date(date)'), $entry['dates'])->delete(),
                    'resumed' => self::repause($user, $entry['rows']),
                    default => null,
                };
            }
        });
    }

    // ── internals ────────────────────────────────────────────────────

    private function add(string $kind, string $type, Model $model): void
    {
        $entry = ['kind' => $kind, 'type' => $type, 'id' => $model->getKey()];

        if ($kind === 'updated') {
            $entry['before'] = array_intersect_key($model->getRawOriginal(), $model->getDirty());
            if ($entry['before'] === []) {
                return;
            }
        }

        if ($kind === 'deleted') {
            $entry['row'] = $model->getRawOriginal();
            if ($type === 'event') {
                $entry['values'] = DB::table('schedule_event_attribute_values')->where('schedule_event_id', $model->getKey())->get()->map(fn ($r) => (array) $r)->all();
                $entry['links'] = DB::table('schedule_event_task_links')->where('schedule_event_id', $model->getKey())->get()->map(fn ($r) => (array) $r)->all();
            }
        }

        $this->push($entry);
    }

    private function push(array $entry): void
    {
        $this->entries[] = $entry;
    }

    private static function reinsert(User $user, array $entry): void
    {
        $row = $entry['row'];
        if ((int) ($row['user_id'] ?? 0) !== $user->id) {
            return;
        }

        // Same id when it is still free (it nearly always is), so links from elsewhere fit again.
        if (self::table($entry['type'])->where('id', $row['id'])->exists()) {
            unset($row['id']);
            $row['id'] = self::table($entry['type'])->insertGetId($row);
        } else {
            self::table($entry['type'])->insert($row);
        }

        // Attribute values and task links come back as far as their attribute / task still exists.
        foreach (['schedule_event_attribute_values' => $entry['values'] ?? [], 'schedule_event_task_links' => $entry['links'] ?? []] as $pivot => $rows) {
            foreach ($rows as $pivotRow) {
                $pivotRow['schedule_event_id'] = $row['id'];
                unset($pivotRow['id']);
                try {
                    DB::table($pivot)->insertOrIgnore($pivotRow);
                } catch (\Illuminate\Database\QueryException) {
                    // The attribute or task was deleted in the meantime: nothing to link to.
                }
            }
        }
    }

    /** @param  list<array{date: string, note: ?string}>  $rows */
    private static function repause(User $user, array $rows): void
    {
        $now = now();
        SchedulePause::insertOrIgnore(array_map(fn (array $r) => [
            'user_id' => $user->id, 'date' => $r['date'], 'note' => $r['note'], 'created_at' => $now, 'updated_at' => $now,
        ], $rows));
    }

    private static function query(string $type, User $user)
    {
        return ($type === 'event' ? ScheduleEvent::query() : EventTemplate::query())->where('user_id', $user->id);
    }

    private static function table(string $type)
    {
        return DB::table($type === 'event' ? 'schedule_events' : 'event_templates');
    }
}
