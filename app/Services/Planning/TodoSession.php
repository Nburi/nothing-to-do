<?php

namespace App\Services\Planning;

use App\Models\Task;
use App\Planning\Pick\Pick;
use Illuminate\Support\Collection;

/**
 * The day's To-dos as the board shows them: one "To-do session" card (2026-11-24).
 * `tasks` are the To-dos of the next 25 minute session in the order they are worked,
 * `picks` the engine's pick for each (minutes, why), `waiting` the open To-dos that
 * did not fit and stay on the board for the next session or day. `block` is the plan of the
 * work block running or next today (Niels, 2026-11-27), listed under the card; null without one.
 */
final readonly class TodoSession
{
    /**
     * @param  Collection<int, Task>  $tasks
     * @param  list<Pick>  $picks
     */
    public function __construct(
        public Collection $tasks = new Collection,
        public array $picks = [],
        public int $waiting = 0,
        public ?BlockPlan $block = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->tasks->isEmpty();
    }

    public function minutes(): int
    {
        return array_sum(array_map(fn (Pick $p) => $p->minutes, $this->picks));
    }

    public function first(): ?Task
    {
        return $this->tasks->first();
    }
}
