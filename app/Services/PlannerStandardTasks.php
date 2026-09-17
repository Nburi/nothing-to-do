<?php

namespace App\Services;

/**
 * "Standardaufgaben" — a small, fixed catalog of recurring quick-add
 * templates for the Planer (App\Livewire\Planner), for the kind of task that
 * comes up again and again and isn't worth re-typing every time: clearing
 * the To-Do list, or a block of studying. Picking one and filling its short
 * form creates one real, ordinary Task placed straight onto the chosen day
 * (via DayPlanner::moveToDay()) — nothing about the resulting task is
 * special afterward, so it behaves exactly like any hand-typed one on every
 * other page (board, backlog, edit sheet, …).
 *
 * Stateless, like ListConcepts/AppModules/HeaderBadges — no DB-backed
 * config, just a fixed PHP catalog plus the small title-building rules that
 * turn a filled-in form into a Task's actual title/list.
 */
class PlannerStandardTasks
{
    public const CATALOG = [
        'todos_clear' => [
            'label' => 'ToDos erledigen',
            'hint' => 'Feste Zeit zum Abarbeiten offener To-Dos',
            'list' => 'todos',
        ],
        'study' => [
            'label' => 'Lernen',
            'hint' => 'Allgemein, für ein Fach oder eine Prüfung',
            'list' => 'tasks',
        ],
    ];

    /** Default block length (minutes) offered for either template — freely adjustable per placement. */
    public const DEFAULT_DURATION = 25;

    public const MIN_DURATION = 5;

    public const MAX_DURATION = 480;

    public const STUDY_MODES = [
        'general' => 'Allgemein',
        'subject' => 'Ein Fach',
        'exam' => 'Für eine Prüfung',
    ];

    public static function isValidKey(string $key): bool
    {
        return array_key_exists($key, self::CATALOG);
    }

    public static function label(string $key): string
    {
        return self::CATALOG[$key]['label'] ?? $key;
    }

    public static function listFor(string $key): string
    {
        return self::CATALOG[$key]['list'] ?? 'tasks';
    }

    public static function isValidStudyMode(string $mode): bool
    {
        return array_key_exists($mode, self::STUDY_MODES);
    }

    /** "Lernen" / "Lernen: Mathematik" / "Lernen für Prüfung: Mathematik" — the one place this title shape is built, so every caller (free text or a linked exam) stays consistent. */
    public static function studyTitle(string $mode, ?string $subject = null): string
    {
        $subject = trim((string) $subject);

        return match ($mode) {
            'subject' => $subject === '' ? 'Lernen' : "Lernen: {$subject}",
            'exam' => $subject === '' ? 'Lernen für Prüfung' : "Lernen für Prüfung: {$subject}",
            default => 'Lernen',
        };
    }
}
