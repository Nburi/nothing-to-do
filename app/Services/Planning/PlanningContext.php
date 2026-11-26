<?php

namespace App\Services\Planning;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Planning\Block;
use App\Planning\Category;
use App\Planning\DeadlineFeasibility;
use App\Planning\Pick\PickCandidate;
use App\Planning\PlannedTask;
use App\Planning\SessionSlot;
use App\Planning\SlotCalculator;
use App\Planning\StartBy;
use App\Services\Projects\NextSteps;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * One read of everything the planning engines need for a user at a moment:
 * the open To-dos and Tasks, the categories, the blocks and 25 minute slots
 * from today to the horizon, the block running now. Built once per request
 * by Suggestions and Capacity so both see the same plan. Read-only.
 */
final class PlanningContext
{
    /** Look at least two weeks ahead, and as far as the latest deadline (capped). */
    public const HORIZON_DAYS = 14;

    public const MAX_HORIZON_DAYS = 60;

    private ?StartBy $startBy = null;

    /** @var list<int>|null open steps of projects and groups the board does not show (NextSteps), read once per context */
    private ?array $hiddenStepIds = null;

    /**
     * @param  Collection<int, Task>  $tasks  keyed by id, in board order
     * @param  list<PickCandidate>  $candidates
     * @param  array<int, Category>  $categories
     * @param  array<string, list<Block>>  $blocks  yesterday … horizon
     * @param  array<string, list<SessionSlot>>  $slots  today … horizon
     */
    private function __construct(
        public readonly User $user,
        public readonly CarbonImmutable $now,
        public readonly string $today,
        public readonly Collection $tasks,
        public readonly array $candidates,
        public readonly array $categories,
        public readonly array $blocks,
        public readonly array $slots,
        public readonly ?Category $category,
        public readonly ?CarbonImmutable $nextBlockAt,
    ) {}

    /** @param  list<int>|null  $hiddenStepIds  what the caller already knows from NextSteps (the board has built its cards), else read on first use */
    public static function for(User $user, ?CarbonImmutable $now = null, WeekPlanReader $reader = new WeekPlanReader, ?array $hiddenStepIds = null): self
    {
        $now = UserClock::now($user, $now);
        $today = $now->toDateString();

        // Board order (step 4.8): To-dos first, then each task by its place in the shared order. A step
        // of a project or group has the place of its card; its own order only settles steps of one card.
        $cardPlace = [
            'project' => Project::forUser($user)->pluck('board_position', 'id'),
            'group' => TaskGroup::forUser($user)->pluck('board_position', 'id'),
        ];
        $place = fn (Task $t): int => match (true) {
            $t->project_id !== null => $cardPlace['project']->get($t->project_id),
            $t->group_id !== null => $cardPlace['group']->get($t->group_id),
            default => $t->board_position,
        } ?? PHP_INT_MAX;

        $tasks = Task::forUser($user)->active()->whereIn('list', Task::WORK_LISTS)->with('stage:id,target_date')->get()
            ->sortBy(fn (Task $t) => [$t->list === 'todos' ? 0 : 1, $place($t), $t->sort_order, $t->created_at?->getTimestamp() ?? 0, $t->id])
            ->values();

        $candidates = $tasks->values()->map(fn (Task $t, int $i) => new PickCandidate($t->toPlannedTask(), $i))->all();
        $latest = collect($candidates)->map(fn (PickCandidate $c) => $c->task->bindingDeadline())->filter()->max();
        $horizon = min(
            max($now->addDays(self::HORIZON_DAYS - 1)->toDateString(), $latest ?? $today),
            $now->addDays(self::MAX_HORIZON_DAYS - 1)->toDateString(),
        );

        $categories = $reader->categories($user);
        $yesterday = $now->subDay()->toDateString();
        $blocks = $reader->blocks($user, $yesterday, $horizon);
        $calculator = new SlotCalculator($categories);
        $slots = array_map(fn (array $b) => $calculator->slots($b), array_diff_key($blocks, [$yesterday => true]));

        $context = new self(
            $user,
            $now,
            $today,
            $tasks->keyBy('id'),
            $candidates,
            $categories,
            $blocks,
            $slots,
            self::categoryAt([...$blocks[$yesterday] ?? [], ...$blocks[$today] ?? []], $categories, $now),
            self::nextSlotStart($slots, $now),
        );
        $context->hiddenStepIds = $hiddenStepIds;

        return $context;
    }

    /** @return list<int> open steps the board does not show: the pick engine must not suggest them */
    public function hiddenStepIds(): array
    {
        return $this->hiddenStepIds ??= app(NextSteps::class)->hiddenTaskIds($this->user);
    }

    /** @return list<PlannedTask> */
    public function plannedTasks(): array
    {
        return array_map(fn (PickCandidate $c) => $c->task, $this->candidates);
    }

    public function startBy(): StartBy
    {
        return $this->startBy ??= (new DeadlineFeasibility($this->categories))->startBy($this->plannedTasks(), $this->slots, $this->now);
    }

    /**
     * The pomodoro category whose block is running now, or null. A fixed
     * appointment inside the block wins (E1); of overlapping pomodoro blocks
     * the one that started first decides (E2).
     *
     * @param  list<Block>  $blocks
     * @param  array<int, Category>  $categories
     */
    private static function categoryAt(array $blocks, array $categories, CarbonImmutable $now): ?Category
    {
        $t = $now->getTimestamp();
        $running = array_values(array_filter($blocks, fn (Block $b) => $b->start->getTimestamp() <= $t && $t < $b->end->getTimestamp()));
        usort($running, fn (Block $a, Block $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        $pomodoro = null;
        foreach ($running as $block) {
            $category = $block->categoryId === null ? null : ($categories[$block->categoryId] ?? null);
            if (! $category?->pomodoro) {
                return null;
            }
            $pomodoro ??= $category;
        }

        return $pomodoro;
    }

    /** @param  array<string, list<SessionSlot>>  $slots */
    private static function nextSlotStart(array $slots, CarbonImmutable $now): ?CarbonImmutable
    {
        foreach ($slots as $daySlots) {
            foreach ($daySlots as $slot) {
                if ($slot->start->getTimestamp() > $now->getTimestamp()) {
                    return $slot->start;
                }
            }
        }

        return null;
    }
}
