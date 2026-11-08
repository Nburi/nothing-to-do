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

    /** @return list<Block> sorted by start */
    public function blocksOn(string $date): array
    {
        $blocks = [];
        $weekday = CarbonImmutable::parse($date)->dayOfWeekIso;

        if (! $this->isPaused($date)) {
            foreach ($this->templates as $template) {
                if (! $template->occursOnWeekday($weekday)) {
                    continue;
                }

                $change = $this->changes[$template->id.'|'.$date] ?? null;
                if ($change?->cancelled) {
                    continue;
                }

                $blocks[] = $this->block(
                    $date,
                    $change?->start ?? $template->start,
                    $change?->durationMinutes ?? $template->durationMinutes,
                    $template->categoryId,
                    $template->id,
                    $template->title,
                );
            }
        }

        foreach ($this->oneOffs as $oneOff) {
            if ($oneOff->date === $date) {
                $blocks[] = $this->block($date, $oneOff->start, $oneOff->durationMinutes, $oneOff->categoryId, null, $oneOff->title);
            }
        }

        usort($blocks, fn (Block $a, Block $b) => [$a->start->getTimestamp(), $a->templateId ?? PHP_INT_MAX]
            <=> [$b->start->getTimestamp(), $b->templateId ?? PHP_INT_MAX]);

        return $blocks;
    }

    private function block(string $date, string $start, int $duration, ?int $categoryId, ?int $templateId, ?string $title): Block
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
        );
    }
}
