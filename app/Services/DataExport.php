<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

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
 */
class DataExport
{
    /** Bumped when the shape of the file changes in a way an importer would care about. */
    public const FORMAT_VERSION = 1;

    private const SETTINGS = [
        'daily_task_goal', 'timezone_offset', 'timezone_auto_dst', 'list_concept', 'default_page',
        'hidden_modules', 'header_badges', 'planner_enabled', 'prepare_time_of_day',
        'day_start_time', 'day_end_time', 'pomodoro_work', 'pomodoro_short_break',
        'pomodoro_long_break', 'pomodoro_long_every', 'pomodoro_autostart',
        'homework_preview_enabled', 'deadline_preview_enabled', 'deadline_preview_days',
    ];

    private const TASK = [
        'id', 'title', 'list', 'project_id', 'group_id', 'is_today', 'today_date', 'is_important',
        'deadline', 'due_date', 'notes', 'duration_minutes', 'repeat_rule', 'is_completed',
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
            'projects' => $user->projects()->orderBy('id')->get()
                ->map(fn (Model $p) => Arr::only($p->toArray(), ['id', 'name', 'brainstorm', 'external_url', 'created_at']))->all(),
            'task_groups' => $user->taskGroups()->with('notes')->orderBy('id')->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'notes' => $g->notes->pluck('content')->all(),
                    'created_at' => $g->created_at?->toIso8601String(),
                ])->all(),
            // Entries this person wrote, shared with a class or not — their own
            // words. Someone else's entry in the same class is not their data.
            'agenda_entries' => $user->agendaEntries()->withCompletionState($user)->withPrivateNoteFor($user)->orderBy('id')->get()
                ->map(fn (AgendaEntry $e) => [
                    ...Arr::only($e->toArray(), ['id', 'type', 'subject', 'title', 'notes', 'date', 'created_at']),
                    'shared_with_class' => $e->agenda_space_id !== null,
                    'done' => $e->isDoneFor($user),
                    'private_note' => $e->privateNoteFor($user),
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
