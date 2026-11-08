<?php

namespace App\Services\Planning;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\SchedulePause;
use App\Models\User;
use App\Planning\Block;
use App\Planning\Category;
use App\Planning\OneOffBlock;
use App\Planning\OneOffChange;
use App\Planning\SessionSlot;
use App\Planning\SlotCalculator;
use App\Planning\Template;
use App\Planning\WeekPlan;
use App\Support\UserClock;
use Carbon\CarbonImmutable;

/**
 * Reads a user's week plan from the old tables into the pure engine
 * (App\Planning\WeekPlan) without writing anything (refactor steps 1.2 / 1.3).
 *
 * The old app materialises template occurrences into schedule_events rows on
 * the fly. Reading must not, so the rows are taken for what they are:
 *
 * - a recurring template      → Template (fills every date that has no row yet)
 * - a row with a template id  → OneOffChange for that date ("this week only":
 *                               edited, or cancelled = tombstone)
 * - a row without template id → OneOffBlock (a Termin or a placed block; a pause
 *                               never hides it, same as ScheduleEvent::scopeVisible)
 * - schedule_pauses           → paused dates
 *
 * A row whose template no longer repeats on that weekday (or was turned into a
 * one-off) is kept as the old app shows it: as a block on that date that a
 * pause still hides.
 */
final class WeekPlanReader
{
    /** @return array<int, Category> keyed by category id */
    public function categories(User $user): array
    {
        return EventCategory::forUser($user)->with('areas')->get()
            ->mapWithKeys(fn (EventCategory $c) => [$c->id => $c->toPlanningCategory()])
            ->all();
    }

    public function weekPlan(User $user, string $from, string $to): WeekPlan
    {
        $templates = EventTemplate::forUser($user)->recurring()->get()->keyBy('id');
        $paused = SchedulePause::forUser($user)->forRange($from, $to)->get()
            ->map(fn (SchedulePause $p) => $p->date->toDateString())->unique()->values()->all();

        $changes = [];
        $oneOffs = [];
        $rows = ScheduleEvent::forUser($user)->forRange($from, $to)->orderBy('id')->get();
        foreach ($rows as $row) {
            $date = $row->date->toDateString();
            $template = $row->template_id === null ? null : $templates->get($row->template_id);

            if ($template !== null && $template->occursOn($row->date)) {
                $recategorised = ! $row->is_cancelled && $row->category_id !== $template->category_id;
                $changes[] = new OneOffChange($template->id, $date, $row->start_time, $row->durationMinutes(), $row->is_cancelled || $recategorised);

                // This week only with another category: the occurrence becomes a
                // block of its own, still hidden by a pause like any template row.
                if ($recategorised && ! in_array($date, $paused, true)) {
                    $oneOffs[] = new OneOffBlock($date, $row->start_time, $row->durationMinutes(), $row->category_id, $row->title);
                }

                continue;
            }

            if ($row->is_cancelled || ($row->template_id !== null && in_array($date, $paused, true))) {
                continue;
            }

            $oneOffs[] = new OneOffBlock($date, $row->start_time, $row->durationMinutes(), $row->category_id, $row->title);
        }

        return new WeekPlan(
            UserClock::timezone($user, CarbonImmutable::parse($from)),
            $templates->map(fn (EventTemplate $t) => new Template(
                $t->id,
                $t->recurrenceDays(),
                $t->default_start ?: '08:00',
                (int) $t->duration,
                $t->category_id,
                $t->displayName(),
            ))->values()->all(),
            $changes,
            $oneOffs,
            $paused,
        );
    }

    /**
     * Every date from $from to $to with its blocks.
     *
     * @return array<string, list<Block>>
     */
    public function blocks(User $user, string $from, string $to): array
    {
        $plan = $this->weekPlan($user, $from, $to);
        $blocks = [];
        foreach (self::dates($from, $to) as $date) {
            $blocks[$date] = $plan->blocksOn($date);
        }

        return $blocks;
    }

    /**
     * The 25 minute sessions of every date (capacity, R10).
     *
     * @param  array<int, Category>|null  $categories
     * @return array<string, list<SessionSlot>>
     */
    public function slots(User $user, string $from, string $to, ?array $categories = null): array
    {
        $calculator = new SlotCalculator($categories ?? $this->categories($user));

        return array_map(fn (array $blocks) => $calculator->slots($blocks), $this->blocks($user, $from, $to));
    }

    /** @return list<string> */
    public static function dates(string $from, string $to): array
    {
        $dates = [];
        for ($d = CarbonImmutable::parse($from); $d->lte(CarbonImmutable::parse($to)); $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return $dates;
    }
}
