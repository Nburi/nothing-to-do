<?php

namespace App\Services;

use App\Models\TaskSession;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Everything a person has written into the app, as one plain array a
 * controller can hand back as a JSON download — the "sichere vorher, was du
 * behalten möchtest" that the account-deletion card has always asked for
 * without offering a way to do it.
 *
 * Only ever the requesting user's own rows (each read goes through the owner
 * relation), and deliberately an allow-list of fields per section rather than
 * "dump the model": a column added to `users` or `tasks` next year must not
 * start leaking into a download nobody re-audited. Never included: the
 * password hash, remember token, API/Sanctum tokens, push endpoints and keys,
 * other people's agenda entries in a shared class, or presence timestamps.
 *
 * Fields are picked with Arr::only() so a section keeps working when a column
 * does not exist yet on this schema (features land on independent branches).
 *
 * Projekte, Gruppen and the Agenda are gone from the app, but their tables
 * still hold what people wrote there. Those sections are read straight from
 * the tables (no models left), so a download still contains everything.
 */
class DataExport
{
    /** Bumped when the shape of the file changes in a way an importer would care about. */
    public const FORMAT_VERSION = 1;

    private const SETTINGS = [
        'daily_task_goal', 'timezone_offset', 'timezone_auto_dst', 'list_concept', 'default_page',
        'hidden_modules', 'header_badges', 'planner_enabled',
        'day_start_time', 'day_end_time', 'pomodoro_work', 'pomodoro_short_break',
        'pomodoro_long_break', 'pomodoro_long_every', 'pomodoro_autostart',
        'deadline_preview_enabled', 'deadline_preview_days',
    ];

    private const TASK = [
        'id', 'title', 'list', 'project_id', 'group_id', 'stage_id', 'waits_on_id', 'area_id', 'size', 'first_step', 'is_today',
        'today_date', 'is_important', 'deadline', 'due_date', 'notes', 'duration_minutes', 'repeat_rule', 'is_completed',
        'completed_at', 'created_at', 'updated_at',
    ];

    /** @return array<string, mixed> */
    public static function for(User $user): array
    {
        return [
            'meta' => [
                'app' => 'nothing-to-do',
                'format_version' => self::FORMAT_VERSION,
                'exported_at' => now()->toIso8601String(),
            ],
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'settings' => Arr::only($user->toArray(), self::SETTINGS),
            'tasks' => $user->tasks()->orderBy('id')->get()
                ->map(fn (Model $t) => Arr::only($t->toArray(), self::TASK))->all(),
            // The days a task was split across (Planer "Aufteilen"), one row per part.
            'task_parts' => TaskSession::query()->whereHas('task', fn ($q) => $q->forUser($user))->orderBy('task_id')->orderBy('planned_date')->get()
                ->map(fn (TaskSession $p) => [
                    'task_id' => $p->task_id,
                    'date' => $p->planned_date->toDateString(),
                    'minutes' => $p->minutes,
                    'done' => $p->isDone(),
                ])->all(),
            'reminders' => $user->reminders()->orderBy('remind_on')->orderBy('id')->get()
                ->map(fn (Reminder $r) => [
                    'title' => $r->title,
                    'remind_on' => $r->remind_on->toDateString(),
                    'notify_evening_before' => $r->notify_evening_before,
                    'notify_on_day' => $r->notify_on_day,
                    'acknowledged' => $r->isAcknowledged(),
                ])->all(),
            'projects' => DB::table('projects')->where('user_id', $user->id)->orderBy('id')
                ->get(['id', 'name', 'deadline', 'brainstorm', 'external_url', 'created_at'])
                ->map(fn ($p) => [
                    ...(array) $p,
                    // Step 4.3: the roadmap.
                    'stages' => DB::table('project_stages')->where('project_id', $p->id)->orderBy('sort_order')->orderBy('id')
                        ->get(['id', 'name', 'target_date'])->map(fn ($s) => (array) $s)->all(),
                ])->all(),
            'task_groups' => DB::table('task_groups')->where('user_id', $user->id)->orderBy('id')
                ->get(['id', 'name', 'list', 'project_id', 'created_at'])
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'list' => $g->list,
                    'became_project_id' => $g->project_id,
                    'notes' => DB::table('group_notes')->where('task_group_id', $g->id)->orderBy('id')->pluck('content')->all(),
                    'created_at' => $g->created_at,
                ])->all(),
            // Entries this person wrote, shared with a class or not — their own
            // words. Someone else's entry in the same class is not their data.
            'agenda_entries' => DB::table('agenda_entries')->where('user_id', $user->id)->orderBy('id')
                ->get(['id', 'type', 'subject', 'title', 'notes', 'date', 'agenda_space_id', 'created_at'])
                ->map(fn ($e) => [
                    ...Arr::only((array) $e, ['id', 'type', 'subject', 'title', 'notes', 'date', 'created_at']),
                    'shared_with_class' => $e->agenda_space_id !== null,
                    'done' => DB::table('agenda_entry_completions')->where('agenda_entry_id', $e->id)->where('user_id', $user->id)->exists(),
                    'private_note' => DB::table('agenda_entry_notes')->where('agenda_entry_id', $e->id)->where('user_id', $user->id)->value('notes'),
                ])->all(),
            'craft_ideas' => $user->craftIdeas()->orderBy('id')->get()
                ->map(fn (Model $c) => Arr::only($c->toArray(), ['id', 'title', 'where_to_begin', 'is_done', 'created_at']))->all(),
            'event_categories' => $user->eventCategories()->with('customAttributes')->orderBy('id')->get()
                ->map(fn ($c) => [
                    ...Arr::only($c->toArray(), ['id', 'name', 'color', 'pomodoro_enabled']),
                    'attributes' => $c->customAttributes
                        ->map(fn ($a) => Arr::only($a->toArray(), ['name', 'type', 'options', 'unit']))->all(),
                ])->all(),
            'event_templates' => $user->eventTemplates()->orderBy('id')->get()
                ->map(fn (Model $t) => Arr::only($t->toArray(), ['id', 'category_id', 'name', 'color', 'duration', 'default_start', 'is_recurring', 'recurrence', 'buffer_before', 'buffer_after']))->all(),
            'schedule_events' => $user->scheduleEvents()->orderBy('date')->orderBy('start_time')->get()
                ->map(fn (Model $e) => Arr::only($e->toArray(), ['id', 'category_id', 'title', 'color', 'date', 'start_time', 'end_time', 'is_cancelled', 'buffer_before', 'buffer_after']))->all(),
            'schedule_pauses' => $user->schedulePauses()->orderBy('date')->get()
                ->map(fn (Model $p) => Arr::only($p->toArray(), ['date', 'note']))->all(),
        ];
    }
}
