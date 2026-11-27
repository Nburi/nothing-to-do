<?php

namespace App\Services\Planning;

use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The rest of the day's work block as the board shows it under the To-do session card
 * (Niels, 2026-11-27): the tasks the engine would work, session after session, from the
 * slot running or next to the end of that block. It is the pick engine's own ranking filled
 * into the block's 25 minute slots (TodoBundler), not a second planner; nothing is saved.
 *
 * `sessions` are the filled slots in time order: `start` is the slot's start, `rows` its
 * tasks with their minutes. A task longer than a slot takes the slots after it as well, so
 * their starts do not appear. `waiting` is what did not fit into the block's slots.
 */
final readonly class BlockPlan
{
    /**
     * @param  list<array{start: CarbonImmutable, rows: list<array{task: Task, minutes: int}>}>  $sessions
     */
    public function __construct(
        public string $name,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public bool $running,
        public array $sessions = [],
        public int $waiting = 0,
    ) {}

    public function isEmpty(): bool
    {
        return $this->sessions === [];
    }

    /** @return Collection<int, Task> */
    public function tasks(): Collection
    {
        return collect($this->sessions)->flatMap(fn (array $s) => array_column($s['rows'], 'task'))->values();
    }

    public function minutes(): int
    {
        return (int) collect($this->sessions)->sum(fn (array $s) => array_sum(array_column($s['rows'], 'minutes')));
    }
}
