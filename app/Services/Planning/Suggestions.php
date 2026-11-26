<?php

namespace App\Services\Planning;

use App\Models\Evening;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\Pick\Pick;
use App\Planning\Pick\PickCandidate;
use App\Planning\Pick\PickEngine;
use App\Planning\Pick\TodoBundler;
use App\Services\Projects\NextSteps;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

/**
 * Refactor step 1.3: the pick engine wired to a user's tasks, week plan and
 * evening (R1, R5, R10; risks F3, F4).
 *
 * - Candidates are the user's open To-dos and Tasks. Inbox and Later are never
 *   suggested as work (Later comes back only as a free-time idea, step 3.4).
 * - Only inside a pomodoro block is work suggested; its category filters the
 *   areas. Outside, the board learns when the next block begins instead
 *   (REQUIREMENTS § 3.7, A19). See PlanningContext for E1 and E2.
 * - The battery and the "Another" skips live on tonight's Evening row. Skips
 *   are the swap log of risk F3. When every candidate was skipped, "Another"
 *   starts over at the top instead of running dry.
 * - Inside a running session the next task after Done comes from the same
 *   ranking (nextInSession); outside blocks the user chose to work, so there
 *   is no area filter then.
 *
 * - Projects and groups (step 4.3): only the steps their card shows may be
 *   suggested; the steps waiting on the Projekte page are excluded (NextSteps).
 *
 * - To-do session (2026-11-24): the loose To-dos are bundled into one 25 minute session
 *   (todoSession). Inside a session, after a To-do the next pick stays a To-do (nextInSession).
 *   Both rank at Okay: deadline work first, then „Wichtig", then the board order.
 *
 * Reads never write, except the two explicit actions (battery, Another).
 */
final class Suggestions
{
    public function __construct(
        private readonly WeekPlanReader $reader = new WeekPlanReader,
        private readonly PickEngine $engine = new PickEngine,
        private readonly TodoBundler $bundler = new TodoBundler,
    ) {}

    /** @param  PlanningContext|null  $context  a read of the plan the caller already has (the board builds one per render) */
    public function current(User $user, ?CarbonImmutable $now = null, ?PlanningContext $context = null): Suggestion
    {
        $context ??= PlanningContext::for($user, $now, $this->reader);

        return $this->suggest($context, $this->evening($user, $context->today));
    }

    private function suggest(PlanningContext $context, ?Evening $evening): Suggestion
    {
        $battery = $evening?->batteryLevel() ?? Battery::Okay;

        if ($context->category === null) {
            return new Suggestion(SuggestionStatus::OutsideBlocks, $battery, nextBlockAt: $context->nextBlockAt);
        }

        $all = $this->rank($context, $battery, []);
        $picks = $this->rank($context, $battery, $evening?->skippedTaskIds() ?? []);
        $picks = $picks === [] ? $all : $picks;

        if ($picks === []) {
            return new Suggestion(SuggestionStatus::Empty, $battery, categoryId: $context->category->id, nextBlockAt: $context->nextBlockAt);
        }

        return $this->suggestion($picks[0], $context, $battery, count($all) - 1);
    }

    /**
     * The To-do session: the most urgent loose To-dos that fit into 25 minutes, in the order they
     * are worked. Not tied to a block or the battery: it is what the board offers as one card.
     */
    public function todoSession(User $user, ?CarbonImmutable $now = null): TodoSession
    {
        $context = PlanningContext::for($user, $now, $this->reader);
        $all = $this->rank($context, Battery::Okay, [], todosOnly: true);
        $picks = $this->bundler->bundle($all);

        return new TodoSession(
            collect($picks)->map(fn (Pick $p) => $context->tasks[$p->taskId])->values(),
            $picks,
            count($all) - count($picks),
        );
    }

    /** "Another": skip tonight's current pick and show the next one. */
    public function another(User $user, ?CarbonImmutable $now = null): Suggestion
    {
        // One read of the plan: skipping changes the evening, not the tasks or the week.
        $context = PlanningContext::for($user, $now, $this->reader);
        $current = $this->suggest($context, $this->evening($user, $context->today));
        if (! $current->hasPick()) {
            return $current;
        }

        $evening = $this->eveningForWrite($user, $context->today);
        $skipped = array_values(array_unique([...$evening->skippedTaskIds(), $current->pick->taskId]));

        if ($this->rank($context, $evening->batteryLevel(), $skipped) === []) {
            $skipped = []; // everything was skipped once: start over at the top
        }

        $evening->update(['skipped_task_ids' => $skipped]);

        return $this->suggest($context, $evening);
    }

    /** The battery check-in (G4). Null = skipped, which counts as Okay. */
    public function setBattery(User $user, ?Battery $battery, ?CarbonImmutable $now = null): Suggestion
    {
        $today = UserClock::now($user, $now)->toDateString();
        // Any answer (the chip or the question after the reset) means tonight's question is done (G4).
        $this->eveningForWrite($user, $today)->update(['battery' => $battery?->value, 'battery_asked_at' => $now ?? CarbonImmutable::now()]);

        return $this->current($user, $now);
    }

    /** The task that follows $afterTaskId inside a running session, or null when there is none. */
    public function nextInSession(User $user, int $afterTaskId, ?CarbonImmutable $now = null): ?int
    {
        // A free-time idea started with "Jetzt machen" (step 3.4) never leads into work:
        // free time is not filled by the app (R6, CONVENTIONS 12). Done ends the session.
        if (Task::forUser($user)->whereKey($afterTaskId)->value('list') === Task::LATER) {
            return null;
        }

        $context = PlanningContext::for($user, $now, $this->reader);
        $evening = $this->evening($user, $context->today);
        $battery = $evening?->batteryLevel() ?? Battery::Okay;

        $exclude = [$afterTaskId, ...($evening?->skippedTaskIds() ?? [])];

        // To-do session: after a To-do the next pick stays a To-do. FocusSession ends the session
        // (break) once less than five minutes are left, so the 25 minutes are what stops it.
        if ($context->tasks->get($afterTaskId)?->list === 'todos') {
            $todos = $this->rank($context, Battery::Okay, $exclude, todosOnly: true);
            $todos = $todos === [] ? $this->rank($context, Battery::Okay, [$afterTaskId], todosOnly: true) : $todos;
            if ($todos !== []) {
                return $todos[0]->taskId;
            }
        }

        $picks = $this->rank($context, $battery, $exclude);
        $picks = $picks === [] ? $this->rank($context, $battery, [$afterTaskId]) : $picks;

        return $picks[0]->taskId ?? null;
    }

    /** @var array<int, list<int>> hidden project and group steps per user, read once per instance */
    private array $hidden = [];

    /**
     * @param  bool  $todosOnly  only loose To-dos: project and group steps live in their own cards
     * @return list<Pick>
     */
    private function rank(PlanningContext $context, Battery $battery, array $exclude, bool $todosOnly = false): array
    {
        $hidden = $this->hidden[$context->user->id] ??= app(NextSteps::class)->hiddenTaskIds($context->user);
        $candidates = $todosOnly
            ? array_values(array_filter($context->candidates, function (PickCandidate $c) use ($context) {
                $task = $context->tasks[$c->id()];

                return $task->list === 'todos' && $task->project_id === null && $task->group_id === null;
            }))
            : $context->candidates;

        return $this->engine->rank($candidates, $battery, $context->category, $context->startBy(), $context->today, [...$exclude, ...$hidden]);
    }

    private function suggestion(Pick $pick, PlanningContext $context, Battery $battery, int $alternatives): Suggestion
    {
        /** @var Task $task */
        $task = $context->tasks[$pick->taskId];

        return new Suggestion(
            SuggestionStatus::Pick,
            $battery,
            $pick,
            $task,
            self::startWith($task),
            $this->why($pick, $context->now),
            $context->category?->id,
            $context->nextBlockAt,
            max(0, $alternatives),
        );
    }

    /** "Start with: …": the task's first step, else the first open checklist line of its notes. */
    public static function startWith(Task $task): ?string
    {
        $step = trim((string) $task->first_step);
        if ($step !== '') {
            return $step;
        }

        if (preg_match('/^\s*[-*] \[ \] (.+)$/m', (string) $task->notes, $m)) {
            return trim(str_replace(['**', '++', '*'], '', $m[1])) ?: null;
        }

        return null;
    }

    private function why(Pick $pick, CarbonImmutable $now): string
    {
        return __('pick.why.'.$pick->reason->value, [
            'deadline' => $pick->deadline === null ? '' : DayLabel::for($pick->deadline, $now),
            'minutes' => $pick->minutes,
        ]);
    }

    private function evening(User $user, string $date): ?Evening
    {
        return Evening::for($user, $date);
    }

    private function eveningForWrite(User $user, string $date): Evening
    {
        return Evening::forWrite($user, $date);
    }
}
