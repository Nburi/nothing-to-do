<?php

namespace App\Shell;

use App\Models\Task;
use Carbon\CarbonInterface;

/**
 * How a task reads on a card, row or pile in the new shell: area, size and dates as short
 * German (or English) labels. One place, so Werkbank and Stapel say the same thing (F12).
 */
final class TaskFace
{
    /** Area colours are stored with the week plan's colour names; they map onto theme tokens. */
    private const AREA_TOKENS = [
        'overprint' => 'var(--t-area-1)',
        'forest' => 'var(--t-area-2)',
        'contour' => 'var(--t-area-3)',
        'signal' => 'var(--t-bad)',
        'ink' => 'var(--t-faint)',
    ];

    public static function areaColor(?string $color): string
    {
        return self::AREA_TOKENS[$color] ?? 'var(--t-faint)';
    }

    public static function size(Task $task): string
    {
        return __('shell.sizes.'.$task->effectiveSize()->value);
    }

    public static function hard(Task $task): ?string
    {
        return $task->hard_deadline ? __('shell.hard', ['date' => self::date($task->hard_deadline)]) : null;
    }

    public static function soft(Task $task): ?string
    {
        return $task->soft_deadline ? __('shell.soft', ['date' => self::date($task->soft_deadline)]) : null;
    }

    /** "Schule · Short · muss bis 12.11." */
    public static function meta(Task $task): string
    {
        $parts = [];

        if ($task->list !== 'inbox') {
            if ($task->list !== Task::LATER) {
                $parts[] = self::size($task);
            }
            if ($hard = self::hard($task)) {
                $parts[] = $hard;
            } elseif ($soft = self::soft($task)) {
                $parts[] = $soft;
            }
        }

        return implode(' · ', $parts);
    }

    private static function date(CarbonInterface $date): string
    {
        return $date->format('j.n.');
    }
}
