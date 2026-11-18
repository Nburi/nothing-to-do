<?php

namespace App\Services\Planning;

use App\Models\Evening;
use App\Models\Task;
use App\Models\User;
use App\Planning\Block;
use App\Planning\FreeSlot;
use App\Planning\Session\Phase;
use App\Planning\Size;
use App\Services\FocusSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Refactor step 3.4: an idea from Later, only in real free time (R6, CONVENTIONS 3, 4, 12; risk F9).
 *
 * Free time (REQUIREMENTS § 4) is time outside every block of the week plan, fixed or pomodoro,
 * between DAY_STARTS and midnight, with at least MIN_MINUTES left. An idea is offered only when
 *
 * - no session runs (work, break or waiting for "Weiter"),
 * - the free slot is not kept "mine",
 * - deadline work is covered: every hard and soft deadline fits into the pomodoro blocks ahead
 *   (the load forecast of step 1.4 has no crunch). Otherwise the board says which one does not.
 *
 * One idea per free slot: the first look at a slot records its idea on tonight's evening row, so a
 * reload or the other device shows the same one. "Andere" swaps it for the next one. Every idea
 * offered today is remembered and never offered again that day; when Later has nothing new left,
 * the slot says "Der Rest gehört dir." On a whole free day the bigger items come first.
 * The app only suggests: nothing is started, moved or planned without a tap.
 */
final class FreeTimeIdeas
{
    /** Less free time left than this: no idea (an idea needs room to start). */
    public const MIN_MINUTES = 30;

    /** Free time starts at 06:00 local; the night before is never offered. */
    public const DAY_STARTS = 6;

    public function __construct(
        private readonly WeekPlanReader $reader = new WeekPlanReader,
        private readonly Capacity $capacity = new Capacity,
    ) {}

    public function current(User $user, ?CarbonImmutable $now = null): IdeaOffer
    {
        $context = PlanningContext::for($user, $now, $this->reader);
        [$slot, $isNow] = $this->slotFor($context);
        $evening = Evening::for($user, $context->today);
        $mine = $slot !== null && $slot->overlaps($evening?->mine_from, $evening?->mine_until);

        $base = fn (IdeaStatus $status, array $more = []) => new IdeaOffer($status, $slot, $isNow, $mine, ...$more);

        if ($this->sessionRuns($user, $context->now)) {
            return $base(IdeaStatus::Session);
        }
        if ($slot === null || ! $isNow) {
            return $base(IdeaStatus::Hidden);
        }
        if ($mine) {
            return $base(IdeaStatus::Mine);
        }

        $ideas = $this->ideas($user, $slot);
        if ($ideas->isEmpty()) {
            return $base(IdeaStatus::Hidden);
        }

        $forecast = $this->capacity->forecastFor($context);
        if ($forecast->hasCrunch()) {
            $id = $forecast->hardLateTaskIds[0] ?? $forecast->softLateTaskIds[0] ?? null;

            return $base(IdeaStatus::Deadlines, ['blocking' => $id !== null ? $context->tasks->get($id) : null]);
        }

        $shown = $evening?->shownIdeaIds() ?? [];
        $sameSlot = $evening?->idea_slot_until?->getTimestamp() === $slot->until->getTimestamp();

        // The slot's idea, unless it was finished or moved meanwhile (or "Andere" ran out: null).
        $idea = $sameSlot && $evening->idea_task_id !== null ? $ideas->firstWhere('id', $evening->idea_task_id) : null;
        if ($idea === null) {
            $idea = $ideas->first(fn (Task $t) => ! in_array($t->id, $shown, true));
            if ($idea === null) {
                return $base(IdeaStatus::Exhausted);
            }
            $shown = $this->claim($user, $context->today, $slot, $idea->id);
        }

        $left = $ideas->filter(fn (Task $t) => $t->id !== $idea->id && ! in_array($t->id, $shown, true))->count();

        return $base(IdeaStatus::Offer, ['task' => $idea, 'alternatives' => $left]);
    }

    /** "Andere": the next idea in this slot, never one offered earlier today (F9). */
    public function another(User $user, ?CarbonImmutable $now = null): IdeaOffer
    {
        $offer = $this->current($user, $now);
        if (! $offer->hasIdea()) {
            return $offer;
        }

        $context = PlanningContext::for($user, $now, $this->reader);
        $evening = Evening::forWrite($user, $context->today);
        $shown = $evening->shownIdeaIds();
        $next = $this->ideas($user, $offer->slot)->first(fn (Task $t) => ! in_array($t->id, $shown, true));

        // No next one: the slot row with no idea says "Later had nothing new left".
        $evening->update([
            'idea_task_id' => $next?->id,
            'idea_slot_until' => $offer->slot->until->utc(),
            'idea_shown_ids' => $next ? [...$shown, $next->id] : $shown,
        ]);

        return $this->current($user, $now);
    }

    /**
     * "Meins behalten": the free slot running now, or else the next one today, stays free.
     * No idea is offered in it (R6, F9). Returns the new state.
     */
    public function keepMine(User $user, ?CarbonImmutable $now = null): IdeaOffer
    {
        $context = PlanningContext::for($user, $now, $this->reader);
        [$slot] = $this->slotFor($context);

        if ($slot !== null) {
            Evening::forWrite($user, $context->today)->update(['mine_from' => $slot->from->utc(), 'mine_until' => $slot->until->utc()]);
        }

        return $this->current($user, $now);
    }

    /** Undo of "Meins behalten" (CONVENTIONS 7: undoable instead of confirmed). */
    public function undoMine(User $user, ?CarbonImmutable $now = null): IdeaOffer
    {
        $context = PlanningContext::for($user, $now, $this->reader);
        Evening::for($user, $context->today)?->update(['mine_from' => null, 'mine_until' => null]);

        return $this->current($user, $now);
    }

    /**
     * The free slot running now (with at least MIN_MINUTES left), or else the next one today.
     *
     * @return array{0: ?FreeSlot, 1: bool} the slot and whether it runs now
     */
    public function slotFor(PlanningContext $context): array
    {
        $now = $context->now;
        $slots = $this->freeSlots($context);

        foreach ($slots as $slot) {
            if ($slot->contains($now) && $slot->minutesLeft($now) >= self::MIN_MINUTES) {
                return [$slot, true];
            }
        }
        foreach ($slots as $slot) {
            if ($slot->from->greaterThan($now) && $slot->minutesLeft($slot->from) >= self::MIN_MINUTES) {
                return [$slot, false];
            }
        }

        return [null, false];
    }

    /**
     * Today's free slots: the gaps between all blocks (fixed and pomodoro, overlaps merged) from
     * DAY_STARTS to midnight, local time. A block from yesterday that runs past midnight counts.
     *
     * @return list<FreeSlot>
     */
    public function freeSlots(PlanningContext $context): array
    {
        $dayStart = $context->now->startOfDay()->setTime(self::DAY_STARTS, 0);
        $dayEnd = $context->now->startOfDay()->addDay();
        $yesterday = $context->now->subDay()->toDateString();

        $busy = [];
        foreach ([...$context->blocks[$yesterday] ?? [], ...$context->blocks[$context->today] ?? []] as $block) {
            /** @var Block $block */
            $from = max($block->start->getTimestamp(), $dayStart->getTimestamp());
            $until = min($block->end->getTimestamp(), $dayEnd->getTimestamp());
            if ($from < $until) {
                $busy[] = [$from, $until];
            }
        }
        usort($busy, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $wholeDay = $busy === [];
        $slots = [];
        $cursor = $dayStart->getTimestamp();
        $tz = $context->now->getTimezone();

        foreach ([...$busy, [$dayEnd->getTimestamp(), $dayEnd->getTimestamp()]] as [$from, $until]) {
            if ($from > $cursor) {
                $slots[] = new FreeSlot(
                    CarbonImmutable::createFromTimestamp($cursor, $tz),
                    CarbonImmutable::createFromTimestamp($from, $tz),
                    wholeDay: $wholeDay,
                    untilMidnight: $from === $dayEnd->getTimestamp(),
                );
            }
            $cursor = max($cursor, $until);
        }

        return $slots;
    }

    /**
     * The open Later items, in the order of the Later list; on a whole free day the bigger ones first
     * (R6: a bolder suggestion, still only a suggestion).
     *
     * @return Collection<int, Task>
     */
    private function ideas(User $user, FreeSlot $slot): Collection
    {
        $ideas = Task::forUser($user)->active()->where('list', Task::LATER)
            ->with('area')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        if (! $slot->wholeDay) {
            return $ideas;
        }

        $rank = [Size::Block->value => 0, Size::Long->value => 1, Size::Short->value => 2, Size::Quick->value => 3];

        return $ideas->values()
            ->sortBy([fn (Task $a, Task $b) => $rank[$a->effectiveSize()->value] <=> $rank[$b->effectiveSize()->value]])
            ->values();
    }

    /**
     * Instants are written in UTC, like every other timestamp of the evening row (Eloquent stores
     * the wall clock as given, so a local instant would read back an hour off).
     *
     * @return list<int> the ideas shown today, the claimed one included
     */
    private function claim(User $user, string $today, FreeSlot $slot, int $taskId): array
    {
        $evening = Evening::forWrite($user, $today);
        $shown = array_values(array_unique([...$evening->shownIdeaIds(), $taskId]));
        $evening->update(['idea_task_id' => $taskId, 'idea_slot_until' => $slot->until->utc(), 'idea_shown_ids' => $shown]);

        return $shown;
    }

    private function sessionRuns(User $user, CarbonImmutable $now): bool
    {
        return app(FocusSession::class)->current($user, $now)->phase !== Phase::Idle;
    }
}
