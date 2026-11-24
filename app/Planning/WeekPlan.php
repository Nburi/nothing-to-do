<?php

namespace App\Planning;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * The week plan as one immutable value: repeating templates, one-off changes
 * ("this week only"), one-off blocks and paused dates. It answers one
 * question, "which blocks are on this date?", with the same per-date rules as
 * the old app (EventTemplate::occursOn, ScheduleEvent::scopeVisible):
 *
 * - a template appears on each of its weekdays,
 * - a one-off change replaces or cancels that one occurrence only,
 * - a paused date hides template blocks, never one-off appointments,
 * - a paused range is one row per date, so a single day can be switched back on.
 *
 * Times are local "HH:MM" in the user's timezone; durations are wall-clock
 * minutes, so the week grid always shows what the user typed.
 */
final readonly class WeekPlan
{
    /** @var array<int, Template> */
    private array $templates;

    /** @var array<string, OneOffChange> */
    private array $changes;

    /** @var array<string, true> */
    private array $pausedDates;

    /**
     * @param  list<Template>  $templates
     * @param  list<OneOffChange>  $changes
     * @param  list<OneOffBlock>  $oneOffs
     * @param  list<string>  $pausedDates
     */
    public function __construct(
        public string $timezone,
        array $templates = [],
        array $changes = [],
        private array $oneOffs = [],
        array $pausedDates = [],
    ) {
        $byId = [];
        foreach ($templates as $template) {
            $byId[$template->id] = $template;
        }
        $this->templates = $byId;

        $byKey = [];
        foreach ($changes as $change) {
            $byKey[$change->key()] = $change; // a later change for the same date wins
        }
        $this->changes = $byKey;

        $this->pausedDates = array_fill_keys($pausedDates, true);
    }

    public function template(int $id): Template
    {
        return $this->templates[$id] ?? throw new InvalidArgumentException("Unknown template {$id}");
    }

    public function withChange(OneOffChange $change): self
    {
        return new self($this->timezone, array_values($this->templates), [...array_values($this->changes), $change], $this->oneOffs, array_keys($this->pausedDates));
    }

    public function withOneOff(OneOffBlock $block): self
    {
        return new self($this->timezone, array_values($this->templates), array_values($this->changes), [...$this->oneOffs, $block], array_keys($this->pausedDates));
    }

    /** Pause every date from $from to $to inclusive (holidays, sick days). */
    public function withPausedRange(string $from, string $to): self
    {
        $dates = array_keys($this->pausedDates);
        for ($d = CarbonImmutable::parse($from); $d->lte(CarbonImmutable::parse($to)); $d = $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        return new self($this->timezone, array_values($this->templates), array_values($this->changes), $this->oneOffs, array_values(array_unique($dates)));
    }

    /** Switch one paused date back on. */
    public function withoutPause(string $date): self
    {
        $dates = array_keys($this->pausedDates);

        return new self($this->timezone, array_values($this->templates), array_values($this->changes), $this->oneOffs, array_values(array_diff($dates, [$date])));
    }

    public function isPaused(string $date): bool
    {
        return isset($this->pausedDates[$date]);
    }

    /**
     * The template blocks a paused date would have had (step 4.1, B6 "ghosts"): what the series and
     * its one-week changes say for that date, minus the ones the user already brought back as a
     * one-off (same time, category and, for a Termin, name). Empty on a date that is not paused.
     *
     * Ghosts are a view for the editor only. blocksOn() never contains them, so they cannot
     * reach capacity, the forecast, the pick engine or a session start: a paused date still
     * produces no work time (R4).
     *
     * @return list<Block> sorted by start
     */
    public function ghostsOn(string $date): array
    {
        if (! $this->isPaused($date)) {
            return [];
        }

        $real = $this->blocksOn($date);
        $ghosts = array_values(array_filter(
            $this->templateBlocksOn($date, false),
            fn (Block $ghost) => ! array_filter($real, fn (Block $block) => $this->sameBlock($ghost, $block)),
        ));
        usort($ghosts, fn (Block $a, Block $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        return $ghosts;
    }

    /** @return list<Block> sorted by start */
    public function blocksOn(string $date): array
    {
        // A paused date keeps only the blocks the user brought back (an exception to the pause).
        $blocks = $this->templateBlocksOn($date, $this->isPaused($date) ? true : null);

        foreach ($this->oneOffs as $oneOff) {
            if ($oneOff->date === $date) {
                $blocks[] = $this->block($date, $oneOff->start, $oneOff->durationMinutes, $oneOff->categoryId, $oneOff->templateId, $oneOff->title, $oneOff->eventId, $oneOff->templateId !== null, $oneOff->tint);
            }
        }

        usort($blocks, fn (Block $a, Block $b) => [$a->start->getTimestamp(), $a->templateId ?? PHP_INT_MAX]
            <=> [$b->start->getTimestamp(), $b->templateId ?? PHP_INT_MAX]);

        return $blocks;
    }

    /**
     * The repeating blocks of a date with their one-week changes applied, pause ignored.
     *
     * @param  bool|null  $exceptions  true: only blocks restored against a pause, false: only the others, null: all
     * @return list<Block>
     */
    private function templateBlocksOn(string $date, ?bool $exceptions = null): array
    {
        $blocks = [];
        $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;

        foreach ($this->templates as $template) {
            if (! $template->occursOnWeekday($weekday)) {
                continue;
            }

            $change = $this->changes[$template->id.'|'.$date] ?? null;
            if ($change?->cancelled || ($exceptions !== null && ($change?->pauseException ?? false) !== $exceptions)) {
                continue;
            }

            $blocks[] = $this->block(
                $date,
                $change?->start ?? $template->start,
                $change?->durationMinutes ?? $template->durationMinutes,
                $template->categoryId,
                $template->id,
                $template->title,
                $change?->eventId,
                // "Changed" only when it differs: a row the old screens materialised unchanged is not an edit.
                $change !== null && ($change->pauseException || $change->start !== $template->start || $change->durationMinutes !== $template->durationMinutes),
                $template->tint,
            );
        }

        return $blocks;
    }

    private function sameBlock(Block $a, Block $b): bool
    {
        return $a->start->equalTo($b->start)
            && $a->end->equalTo($b->end)
            && $a->categoryId === $b->categoryId
            && ($a->categoryId !== null || $a->title === $b->title);
    }

    private function block(string $date, string $start, int $duration, ?int $categoryId, ?int $templateId, ?string $title, ?int $eventId = null, bool $changed = false, ?string $tint = null): Block
    {
        [$h, $m] = array_map('intval', explode(':', $start));
        $endMinutes = $h * 60 + $m + $duration;
        $endDate = CarbonImmutable::parse($date)->addDays(intdiv($endMinutes, 1440))->toDateString();
        $endTime = sprintf('%02d:%02d', intdiv($endMinutes % 1440, 60), $endMinutes % 60);

        return new Block(
            $date,
            CarbonImmutable::parse("{$date} {$start}", $this->timezone),
            CarbonImmutable::parse("{$endDate} {$endTime}", $this->timezone),
            $categoryId,
            $templateId,
            $title,
            $eventId,
            $changed,
            $tint,
        );
    }
}
