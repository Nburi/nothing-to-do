<?php

namespace App\Services;

use App\Models\ScheduleEvent;
use App\Models\User;

/**
 * Pure Pomodoro layout. Given the free-time blocks a user marked and an ordered
 * queue of focus units (one To-Do-Session at most, then one Work-Session per
 * Task-session), it lays out concrete sessions and breaks inside the free time.
 *
 * Rules honoured:
 *   • 1 Task per session; a Task simply contributes several units (no double-booking).
 *   • Short break between sessions; a long break after every `longEvery` focus
 *     sessions (To-Do-Session counts toward the cadence).
 *   • Breaks only sit *between* two sessions in the same block — never trailing,
 *     never spanning an appointment gap (the gap is the break).
 *   • Sessions are loose suggestions; nothing is hard-assigned to a Task.
 *
 * Nothing here touches the database — the Brief persists the result.
 */
class ScheduleGenerator
{
    public function __construct(
        private int $work = 25,
        private int $shortBreak = 5,
        private int $longBreak = 15,
        private int $longEvery = 4,
    ) {}

    public static function forUser(User $user): self
    {
        $p = $user->pomodoro();

        return new self($p['work'], $p['short_break'], $p['long_break'], $p['long_every']);
    }

    /**
     * @param  array<int, array{start:int,end:int}>  $freeBlocks  minutes-from-midnight ranges
     * @param  array<int, array{kind:string,task_id?:int|null}>  $units  ordered focus queue
     * @return array{events: array<int, array<string, mixed>>, placed: int, unplaced: int}
     */
    public function generate(array $freeBlocks, array $units): array
    {
        $blocks = $this->normalizeBlocks($freeBlocks);
        $events = [];
        $blockIdx = 0;
        $cursor = $blocks[0]['start'] ?? 0;
        $sessionCount = 0;
        $placed = 0;
        $unitCount = count($units);

        while ($placed < $unitCount && $blockIdx < count($blocks)) {
            $block = $blocks[$blockIdx];
            $cursor = max($cursor, $block['start']);

            // Session doesn't fit in what's left of this block → try the next block.
            if ($cursor + $this->work > $block['end']) {
                $blockIdx++;
                $cursor = $blocks[$blockIdx]['start'] ?? $cursor;

                continue;
            }

            $unit = $units[$placed];
            $events[] = $this->sessionEvent($unit, $cursor);
            $cursor += $this->work;
            $sessionCount++;
            $placed++;

            // Decide on a break only if another unit still needs placing.
            if ($placed < $unitCount) {
                $break = $sessionCount % $this->longEvery === 0 ? $this->longBreak : $this->shortBreak;

                // Place the break only if it AND the next session fit in this block;
                // otherwise the next session starts fresh in the next block.
                if ($cursor + $break + $this->work <= $block['end']) {
                    $events[] = $this->breakEvent($cursor, $break);
                    $cursor += $break;
                } else {
                    $blockIdx++;
                    $cursor = $blocks[$blockIdx]['start'] ?? $cursor;
                }
            }
        }

        return ['events' => $events, 'placed' => $placed, 'unplaced' => $unitCount - $placed];
    }

    /**
     * How many focus sessions actually fit in the marked free time — computed by
     * the same layout, so the capacity meter never disagrees with the plan.
     *
     * @param  array<int, array{start:int,end:int}>  $freeBlocks
     */
    public function capacity(array $freeBlocks): int
    {
        $blocks = $this->normalizeBlocks($freeBlocks);
        $upperBound = 0;
        foreach ($blocks as $b) {
            $upperBound += intdiv($b['end'] - $b['start'], $this->work) + 1;
        }

        $units = array_fill(0, $upperBound, ['kind' => 'work', 'task_id' => null]);

        return $this->generate($blocks, $units)['placed'];
    }

    /**
     * @param  array<int, array{start:int,end:int}>  $freeBlocks
     * @return array<int, array{start:int,end:int}>
     */
    private function normalizeBlocks(array $freeBlocks): array
    {
        $blocks = array_values(array_filter(
            $freeBlocks,
            fn ($b) => isset($b['start'], $b['end']) && $b['end'] - $b['start'] >= $this->work
        ));

        usort($blocks, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $blocks;
    }

    /** @param array{kind:string,task_id?:int|null} $unit */
    private function sessionEvent(array $unit, int $start): array
    {
        $isTodo = ($unit['kind'] ?? 'work') === 'todo';

        return [
            'type' => $isTodo ? ScheduleEvent::TYPE_TODO : ScheduleEvent::TYPE_WORK,
            'title' => $isTodo ? 'To-Do-Session' : null,
            'color' => 'forest',
            'suggested_task_id' => $isTodo ? null : ($unit['task_id'] ?? null),
            'start_time' => ScheduleEvent::fromMinutes($start),
            'end_time' => ScheduleEvent::fromMinutes($start + $this->work),
            'source' => 'brief',
        ];
    }

    private function breakEvent(int $start, int $length): array
    {
        return [
            'type' => ScheduleEvent::TYPE_BREAK,
            'title' => null,
            'color' => 'ink-faint',
            'suggested_task_id' => null,
            'start_time' => ScheduleEvent::fromMinutes($start),
            'end_time' => ScheduleEvent::fromMinutes($start + $length),
            'source' => 'brief',
        ];
    }
}
