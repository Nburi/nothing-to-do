<?php

namespace App\Services;

/**
 * The one-question quiz that opens the onboarding tutorial (see
 * App\Livewire\Onboarding) — "Warum suchst du gerade eine To-Do-Liste?". A
 * stateless catalog + a pure resolver, mirroring AppModules/ListConcepts:
 * every answer can cast a vote for a ListConcepts key and/or add to the set
 * of AppModules keys the Feature-Galerie step pre-selects. Both are only
 * ever a *pre-selection* — the Konzept and Feature-Galerie steps right
 * after the quiz are just Settings' own pickers, freely overridable.
 */
class OnboardingQuiz
{
    /**
     * In display/vote-tally order. `concept` is null for an answer that
     * deliberately abstains (Bastelideen/Fortschritt/"reinschnuppern") so it
     * combines with any concept answer without skewing the result. `modules`
     * are App\Services\AppModules::CATALOG keys; an answer only ever adds to
     * the pre-selection, never removes from it.
     *
     * @var list<array{key: string, label: string, concept: ?string, modules: list<string>}>
     */
    public const ANSWERS = [
        [
            'key' => 'simple_list',
            'label' => 'Ich will nur eine einfache Liste, ohne Ballast',
            'concept' => 'simple',
            'modules' => [],
        ],
        [
            'key' => 'shared_homework',
            'label' => 'Ich brauche eine geteilte Liste für Hausaufgaben mit meiner Klasse',
            'concept' => 'simple',
            'modules' => ['agenda'],
        ],
        [
            'key' => 'important_urgent',
            'label' => 'Ich verliere den Überblick, was wichtig UND dringend ist',
            'concept' => 'eisenhower',
            'modules' => ['emergency'],
        ],
        [
            'key' => 'mixed_sizes',
            'label' => 'Ich habe kleine Erledigungen und grosse, mehrteilige Vorhaben gleichzeitig',
            'concept' => 'three_things',
            'modules' => ['progress'],
        ],
        [
            'key' => 'in_progress_done',
            'label' => 'Ich will sehen, was gerade in Arbeit ist und was schon fertig',
            'concept' => 'kanban',
            'modules' => [],
        ],
        [
            'key' => 'plan_the_day',
            'label' => 'Ich will meinen ganzen Tag durchplanen — Termine, Fokuszeit, alles',
            'concept' => 'kanban',
            'modules' => ['schedule', 'weekplan', 'prepare'],
        ],
        [
            'key' => 'bored',
            'label' => 'Ich will wissen, was ich mache, wenn mir langweilig ist',
            'concept' => null,
            'modules' => ['crafts'],
        ],
        [
            'key' => 'stay_consistent',
            'label' => 'Ich will dranbleiben und meinen Fortschritt sehen',
            'concept' => null,
            'modules' => ['progress'],
        ],
        [
            'key' => 'just_browsing',
            'label' => 'Ich will einfach mal reinschnuppern',
            'concept' => null,
            'modules' => [],
        ],
    ];

    /** No answer with a concept vote checked (or nothing checked at all) falls back here. */
    public const FALLBACK_CONCEPT = 'simple';

    /**
     * Tallies every checked answer's concept vote and unions their modules.
     * A tie goes to whichever concept's first vote appears earliest in
     * ANSWERS' own order — only a strictly greater tally ever replaces the
     * current leader, and PHP preserves insertion order in the running
     * `$votes` map, so the first concept to reach the eventual max stays on
     * top of any later tie.
     *
     * @param  list<string>  $selectedKeys
     * @return array{concept: string, modules: list<string>}
     */
    public static function resolve(array $selectedKeys): array
    {
        $votes = [];
        $modules = [];

        foreach (self::ANSWERS as $answer) {
            if (! in_array($answer['key'], $selectedKeys, true)) {
                continue;
            }

            if ($answer['concept'] !== null) {
                $votes[$answer['concept']] = ($votes[$answer['concept']] ?? 0) + 1;
            }

            $modules = [...$modules, ...$answer['modules']];
        }

        $concept = self::FALLBACK_CONCEPT;
        $bestCount = 0;

        foreach ($votes as $key => $count) {
            if ($count > $bestCount) {
                $bestCount = $count;
                $concept = $key;
            }
        }

        return [
            'concept' => $concept,
            'modules' => array_values(array_unique($modules)),
        ];
    }
}
