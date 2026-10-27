<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Nicht vergessen" — the one place the rules about reminders live: what may
 * be written (the Livewire sheet, QuickCapture, the REST API and MCP all go
 * through create/update/acknowledge/delete, so they cannot drift), which
 * reminders show as a badge above the Zeitstrahl, and when each push is due.
 *
 * Stateless, like DayPlanner/TaskSuggestor. Every read is scoped to the given
 * user; callers pass records they resolved through the owner relation.
 */
class Reminders
{
    public const TITLE_MAX = 120;

    /** Upcoming reminders one account may hold — a guard against a runaway script, far above real use. */
    public const MAX_UPCOMING = 100;

    /** How far ahead a reminder may be set. */
    public const HORIZON_DAYS = 365;

    /** The badge appears this many days before the day (today included: tomorrow = 1, the day after = 2). */
    public const BADGE_DAYS_AHEAD = 2;

    /** "Smart timing": the push for today goes out this long before the first block's departure. */
    public const LEAD_MINUTES = 30;

    /** …but never earlier than this minute of the day (05:00) — nobody asked to be woken for a pair of shoes. */
    public const EARLIEST_ON_DAY_MINUTE = 300;

    /** An acknowledged reminder stays in the badge row this long, folded into its check mark, before it leaves. */
    public const ACK_LINGER_SECONDS = 8;

    /** Rows older than this many days are deleted by the push command. */
    public const PRUNE_AFTER_DAYS = 30;

    // ── Writes ────────────────────────────────────────────────────────

    /**
     * @param  array{title?: mixed, remind_on?: mixed, notify_evening_before?: mixed, notify_on_day?: mixed}  $data
     *
     * @throws ValidationException
     */
    public static function create(User $user, array $data): Reminder
    {
        $valid = self::validated($user, $data, creating: true);

        if (Reminder::forUser($user)->fromDate($user->localToday())->count() >= self::MAX_UPCOMING) {
            throw ValidationException::withMessages(['title' => 'Du hast schon '.self::MAX_UPCOMING.' kommende Erinnerungen — hake ein paar ab oder lösche welche.']);
        }

        return $user->reminders()->create($valid + ['notify_evening_before' => true, 'notify_on_day' => true])->fresh();
    }

    /**
     * Changing the day makes it a new reminder as far as pushes go: both push
     * stamps and the acknowledgement are cleared, so a moved "Hab ich" doesn't
     * silently swallow the pushes for the new day.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function update(User $user, Reminder $reminder, array $data): Reminder
    {
        $valid = self::validated($user, $data, creating: false, current: $reminder);

        $moved = isset($valid['remind_on']) && $valid['remind_on'] !== $reminder->remind_on->toDateString();

        $reminder->update($valid + ($moved ? ['acknowledged_at' => null, 'notified_before_at' => null, 'notified_on_at' => null] : []));

        return $reminder->fresh();
    }

    /** "Hab ich" (or, with $done = false, "doch noch nicht"). Either way pushes are re-evaluated from scratch by the command. */
    public static function acknowledge(Reminder $reminder, bool $done = true): Reminder
    {
        $reminder->update(['acknowledged_at' => $done ? now() : null]);

        return $reminder->fresh();
    }

    public static function delete(Reminder $reminder): void
    {
        $reminder->delete();
    }

    // ── Reads ─────────────────────────────────────────────────────────

    /**
     * What the strip above the Zeitstrahl shows: open reminders from today up
     * to BADGE_DAYS_AHEAD days ahead, plus ones ticked off a moment ago (they
     * linger a few seconds so the fold-into-a-check moment can play before the
     * chip leaves). A reminder whose day has passed is simply gone from here.
     *
     * @return Collection<int, Reminder>
     */
    public static function badges(User $user): Collection
    {
        $today = $user->localToday();

        return Reminder::forUser($user)
            ->fromDate($today)
            ->whereDate('remind_on', '<=', $today->copy()->addDays(self::BADGE_DAYS_AHEAD)->toDateString())
            ->where(fn ($q) => $q->whereNull('acknowledged_at')->orWhere('acknowledged_at', '>=', now()->subSeconds(self::ACK_LINGER_SECONDS)))
            ->chronological()
            ->get();
    }

    /**
     * Everything from today on, ticked ones included — the "Alle Erinnerungen" sheet.
     *
     * @return Collection<int, Reminder>
     */
    public static function upcoming(User $user): Collection
    {
        return Reminder::forUser($user)->fromDate($user->localToday())->chronological()->limit(self::MAX_UPCOMING)->get();
    }

    /**
     * The open reminders of one day — the Vorbereitung checklist, the Tagesüberblick tile, the notification summary.
     *
     * @return Collection<int, Reminder>
     */
    public static function forDate(User $user, Carbon $date): Collection
    {
        return Reminder::forUser($user)->open()->on($date)->chronological()->get();
    }

    // ── Push timing ───────────────────────────────────────────────────

    /**
     * The pushes that are due right now for this user, each as
     * ['reminder' => Reminder, 'kind' => 'before'|'on', 'silent' => bool].
     * "silent" means the moment already passed before the reminder existed
     * (set up at 21:00 for tomorrow, evening push due 19:00): the command only
     * stamps it as handled — a push seconds after creating it would be noise.
     *
     * Dedup is the notified_*_at stamps, not an exact-minute match, so a
     * delayed or missed cron tick still fires on the next run, like every
     * other reminder in this app.
     *
     * @return list<array{reminder: Reminder, kind: string, silent: bool}>
     */
    public static function dueNotifications(User $user): array
    {
        $now = $user->localNow();
        $today = $user->localToday();
        $minute = $now->hour * 60 + $now->minute;
        $due = [];

        $eveningMinute = self::minutesOf($user->reminder_evening_time ?? '19:00', 19 * 60);

        if ($minute >= $eveningMinute) {
            $dueInstant = $today->copy()->addMinutes($eveningMinute);

            Reminder::forUser($user)->open()->on($today->copy()->addDay())
                ->where('notify_evening_before', true)->whereNull('notified_before_at')
                ->get()
                ->each(function (Reminder $r) use (&$due, $user, $dueInstant) {
                    $due[] = ['reminder' => $r, 'kind' => 'before', 'silent' => self::createdAfter($user, $r, $dueInstant)];
                });
        }

        $todays = Reminder::forUser($user)->open()->on($today)
            ->where('notify_on_day', true)->whereNull('notified_on_at')
            ->get();

        if ($todays->isNotEmpty()) {
            $onDayMinute = self::onDayDueMinute($user, $today);

            if ($minute >= $onDayMinute) {
                $dueInstant = $today->copy()->addMinutes($onDayMinute);

                foreach ($todays as $r) {
                    $due[] = ['reminder' => $r, 'kind' => 'on', 'silent' => self::createdAfter($user, $r, $dueInstant)];
                }
            }
        }

        return $due;
    }

    /**
     * The minute of the (local) day at which today's reminders are pushed: the
     * user's morning time — or, with smart timing on, earlier when the day's
     * first block (its travel time counted in) starts before that plus the
     * lead. Never earlier than EARLIEST_ON_DAY_MINUTE.
     */
    public static function onDayDueMinute(User $user, Carbon $day): int
    {
        $base = self::minutesOf($user->reminder_morning_time ?? '07:00', 7 * 60);

        if (! $user->reminder_smart_timing) {
            return $base;
        }

        ScheduleEvent::materializeRange($user, $day, $day->copy());

        $first = ScheduleEvent::forUser($user)->visible()->forDay($day)->get()
            ->map(fn (ScheduleEvent $e) => $e->occupiedStartMinutes())
            ->min();

        if ($first === null) {
            return $base;
        }

        return max(self::EARLIEST_ON_DAY_MINUTE, min($base, $first - self::LEAD_MINUTES));
    }

    /** @return array{title: string, body: string, url: string} */
    public static function pushPayload(Reminder $reminder, string $kind): array
    {
        return [
            'title' => $kind === 'before' ? 'Morgen nicht vergessen' : 'Heute nicht vergessen',
            'body' => $reminder->title,
            'url' => '/app',
        ];
    }

    // ── Internals ─────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private static function validated(User $user, array $data, bool $creating, ?Reminder $current = null): array
    {
        $today = $user->localToday();

        if (isset($data['title']) && is_string($data['title'])) {
            $data['title'] = trim($data['title']);
        }

        $dateRules = ['date_format:Y-m-d', 'before_or_equal:'.$today->copy()->addDays(self::HORIZON_DAYS)->toDateString()];

        // An existing reminder may keep a day that has since passed (a title edit must not fail on it);
        // anything newly chosen has to lie in the future.
        if ($creating || ! isset($data['remind_on']) || $data['remind_on'] !== $current?->remind_on?->toDateString()) {
            $dateRules[] = 'after_or_equal:'.$today->toDateString();
        }

        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:'.self::TITLE_MAX],
            'remind_on' => [$creating ? 'required' : 'sometimes', ...$dateRules],
            'notify_evening_before' => ['sometimes', 'boolean'],
            'notify_on_day' => ['sometimes', 'boolean'],
        ];

        return Validator::make($data, $rules, [], ['title' => 'Titel', 'remind_on' => 'Datum'])
            ->validate();
    }

    /** "HH:MM" → minutes after midnight; a malformed value falls back rather than breaking the command. */
    private static function minutesOf(string $time, int $fallback): int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            return $fallback;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /** Whether the reminder was created at or after the moment its push became due (both in the user's local time). */
    private static function createdAfter(User $user, Reminder $reminder, Carbon $dueInstant): bool
    {
        $createdLocal = $reminder->created_at->copy()->addMinutes($user->utcOffsetMinutes($reminder->created_at));

        return $createdLocal->greaterThanOrEqualTo($dueInstant);
    }
}
