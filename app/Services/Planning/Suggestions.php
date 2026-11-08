<?php

namespace App\Services\Planning;

use App\Models\Evening;
use App\Models\Task;
use App\Models\User;
use App\Planning\Battery;
use App\Planning\Block;
use App\Planning\Category;
use App\Planning\DeadlineFeasibility;
use App\Planning\Pick\Pick;
use App\Planning\Pick\PickCandidate;
use App\Planning\Pick\PickEngine;
use App\Planning\SlotCalculator;
use App\Planning\StartBy;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Refactor step 1.3: the pick engine wired to a user's tasks, week plan and
 * evening (R1, R5, R10; risks F3, F4).
 *
 * - Candidates are the user's open To-dos and Tasks. Inbox and Later are never
 *   suggested as work (Later comes back only as a free-time idea, step 3.4).
 * - Only inside a pomodoro block is work suggested; its category filters the
 *   areas. Outside, the board learns when the next block begins instead
 *   (REQUIREMENTS § 3.7, A19). A fixed appointment cuts a block (E1); where
 *   pomodoro blocks overlap, the one that started first decides (E2).
 * - The battery and the "Another" skips live on tonight's Evening row. Skips
 *   are the swap log of risk F3. When every candidate was skipped, "Another"
 *   starts over at the top instead of running dry.
 * - Inside a running session the next task after Done comes from the same
 *   ranking (nextInSession); outside blocks the user chose to work, so there
 *   is no area filter then.
 *
 * Reads never write, except the two explicit actions (battery, Another).
 */
final class Suggestions
{
    /** Start-by dates look at least two weeks ahead, and as far as the latest deadline (capped). */
    private const HORIZON_DAYS = 14;

    private const MAX_HORIZON_DAYS = 60;

    public function __construct(
        private readonly WeekPlanReader $reader = new WeekPlanReader,
        private readonly PickEngine $engine = new PickEngine,
    ) {}

    public function current(User $user, ?CarbonImmutable $now = null): Suggestion
    {
        $context = $this->context($user, $now);
        $evening = $this->evening($user, $context['today']);
        $battery = $evening?->batteryLevel() ?? Battery::Okay;

        if ($context['category'] === null) {
            return new Suggestion(SuggestionStatus::OutsideBlocks, $battery, nextBlockAt: $context['nextBlockAt']);
        }

        $all = $this->rank($context, $battery, []);
        $picks = $this->rank($context, $battery, $evening?->skippedTaskIds() ?? []);
        $picks = $picks === [] ? $all : $picks;

        if ($picks === []) {
            return new Suggestion(SuggestionStatus::Empty, $battery, categoryId: $context['category']->id, nextBlockAt: $context['nextBlockAt']);
        }

        return $this->suggestion($picks[0], $context, $battery, count($all) - 1);
    }

    /** "Another": skip tonight's current pick and show the next one. */
    public function another(User $user, ?CarbonImmutable $now = null): Suggestion
    {
        $current = $this->current($user, $now);
        if (! $current->hasPick()) {
            return $current;
        }

        $today = UserClock::now($user, $now)->toDateString();
        $evening = $this->eveningForWrite($user, $today);
        $skipped = array_values(array_unique([...$evening->skippedTaskIds(), $current->pick->taskId]));

        $context = $this->context($user, $now);
        if ($this->rank($context, $evening->batteryLevel(), $skipped) === []) {
            $skipped = []; // everything was skipped once: start over at the top
        }

        $evening->update(['skipped_task_ids' => $skipped]);

        return $this->current($user, $now);
    }

    /** The battery check-in (G4). Null = skipped, which counts as Okay. */
    public function setBattery(User $user, ?Battery $battery, ?CarbonImmutable $now = null): Suggestion
    {
        $today = UserClock::now($user, $now)->toDateString();
        $this->eveningForWrite($user, $today)->update(['battery' => $battery?->value]);

        return $this->current($user, $now);
    }

    /** The task that follows $afterTaskId inside a running session, or null when there is none. */
    public function nextInSession(User $user, int $afterTaskId, ?CarbonImmutable $now = null): ?int
    {
        $context = $this->context($user, $now);
        $evening = $this->evening($user, $context['today']);
        $battery = $evening?->batteryLevel() ?? Battery::Okay;

        $picks = $this->rank($context, $battery, [$afterTaskId, ...($evening?->skippedTaskIds() ?? [])]);
        $picks = $picks === [] ? $this->rank($context, $battery, [$afterTaskId]) : $picks;

        return $picks[0]->taskId ?? null;
    }

    /**
     * Everything the ranking needs, read once.
     *
     * @return array{now:CarbonImmutable,today:string,tasks:Collection<int,Task>,candidates:list<PickCandidate>,categories:array<int,Category>,category:?Category,startBy:StartBy,nextBlockAt:?CarbonImmutable}
     */
    private function context(User $user, ?CarbonImmutable $now): array
    {
        $now = UserClock::now($user, $now);
        $today = $now->toDateString();

        $tasks = Task::forUser($user)->active()->whereIn('list', ['todos', 'tasks'])
            ->orderByRaw("CASE WHEN list = 'todos' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')->orderBy('created_at')->orderBy('id')
            ->get();

        $candidates = $tasks->values()->map(fn (Task $t, int $i) => new PickCandidate($t->toPlannedTask(), $i))->all();
        $latest = $tasks->map(fn (Task $t) => $t->toPlannedTask()->bindingDeadline())->filter()->max();
        $horizon = min(
            max($now->addDays(self::HORIZON_DAYS - 1)->toDateString(), $latest ?? $today),
            $now->addDays(self::MAX_HORIZON_DAYS - 1)->toDateString(),
        );

        $categories = $this->reader->categories($user);
        $yesterday = $now->subDay()->toDateString();
        $blocks = $this->reader->blocks($user, $yesterday, $horizon);
        $calculator = new SlotCalculator($categories);
        $slots = array_map(fn (array $b) => $calculator->slots($b), array_diff_key($blocks, [$yesterday => true]));

        $nextBlockAt = null;
        foreach ($slots as $daySlots) {
            foreach ($daySlots as $slot) {
                if ($slot->start->getTimestamp() > $now->getTimestamp()) {
                    $nextBlockAt = $slot->start;
                    break 2;
                }
            }
        }

        return [
            'now' => $now,
            'today' => $today,
            'tasks' => $tasks->keyBy('id'),
            'candidates' => $candidates,
            'categories' => $categories,
            'category' => $this->categoryAt([...$blocks[$yesterday] ?? [], ...$blocks[$today] ?? []], $categories, $now),
            'startBy' => (new DeadlineFeasibility($categories))->startBy(array_map(fn (PickCandidate $c) => $c->task, $candidates), $slots, $now),
            'nextBlockAt' => $nextBlockAt,
        ];
    }

    /**
     * The pomodoro category whose block is running now (E1, E2), or null.
     *
     * @param  list<Block>  $blocks
     * @param  array<int, Category>  $categories
     */
    private function categoryAt(array $blocks, array $categories, CarbonImmutable $now): ?Category
    {
        $t = $now->getTimestamp();
        $running = array_values(array_filter($blocks, fn (Block $b) => $b->start->getTimestamp() <= $t && $t < $b->end->getTimestamp()));
        usort($running, fn (Block $a, Block $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        $pomodoro = null;
        foreach ($running as $block) {
            $category = $block->categoryId === null ? null : ($categories[$block->categoryId] ?? null);
            if (! $category?->pomodoro) {
                return null; // a fixed appointment wins
            }
            $pomodoro ??= $category;
        }

        return $pomodoro;
    }

    /** @return list<Pick> */
    private function rank(array $context, Battery $battery, array $exclude): array
    {
        return $this->engine->rank($context['candidates'], $battery, $context['category'], $context['startBy'], $context['today'], $exclude);
    }

    private function suggestion(Pick $pick, array $context, Battery $battery, int $alternatives): Suggestion
    {
        /** @var Task $task */
        $task = $context['tasks'][$pick->taskId];

        return new Suggestion(
            SuggestionStatus::Pick,
            $battery,
            $pick,
            $task,
            self::startWith($task),
            $this->why($pick, $context['now']),
            $context['category']?->id,
            $context['nextBlockAt'],
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
            'deadline' => $pick->deadline === null ? '' : $this->dayLabel($pick->deadline, $now),
            'minutes' => $pick->minutes,
        ]);
    }

    /** heute / morgen / weekday within a week / d.m. */
    private function dayLabel(string $date, CarbonImmutable $now): string
    {
        $day = CarbonImmutable::parse($date, $now->getTimezone());
        $days = (int) CarbonImmutable::parse($now->toDateString(), $now->getTimezone())->diffInDays($day, false);

        return match (true) {
            $days <= 0 => __('pick.day.today'),
            $days === 1 => __('pick.day.tomorrow'),
            $days <= 6 => $day->locale(app()->getLocale())->isoFormat('dddd'),
            default => $day->format('j.n.'),
        };
    }

    private function evening(User $user, string $date): ?Evening
    {
        return Evening::query()->where('user_id', $user->id)->whereDate('date', $date)->first();
    }

    private function eveningForWrite(User $user, string $date): Evening
    {
        return $this->evening($user, $date) ?? $user->evenings()->create(['date' => $date]);
    }
}
