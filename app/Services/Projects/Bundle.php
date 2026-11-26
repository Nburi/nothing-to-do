<?php

namespace App\Services\Projects;

use App\Models\ProjectStage;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Step 4.3: one project or group as the board and the Projekte page show it.
 *
 * `visible` holds at most NextSteps::VISIBLE open steps; `list` is the board column the card sits
 * in (null = nothing to do right now, so no card on the board). `progress` is the current stage's
 * for a project (null without stages) and the whole group's for a group. `bundled` are the ids of
 * the open steps the card stands for on the board, visible or not; `hidden` the ones it does not
 * show (never suggested).
 *
 * Step 4.8: `position` is the card's place in the user's shared board order; `resting` is true for
 * a card the calm board keeps off the board (more than NextSteps::CALM cards have work): it shows
 * as a line in the fold under its column and all its steps are hidden from the pick engine.
 */
final readonly class Bundle
{
    /**
     * @param  'project'|'group'  $kind
     * @param  Collection<int, Task>  $visible
     * @param  array{done: int, total: int}|null  $progress
     * @param  list<int>  $bundled
     * @param  list<int>  $hidden
     */
    public function __construct(
        public string $kind,
        public int $id,
        public string $name,
        public ?string $list,
        public Collection $visible,
        public int $more,
        public int $open,
        public ?array $progress,
        public ?ProjectStage $stage,
        public int $stageNumber,
        public int $stageCount,
        public array $bundled,
        public array $hidden,
        public int $position = PHP_INT_MAX,
        public bool $resting = false,
    ) {}

    /** The same card, kept off the board by the calm rule. */
    public function rested(): self
    {
        return new self(
            $this->kind, $this->id, $this->name, $this->list, $this->visible, $this->more, $this->open,
            $this->progress, $this->stage, $this->stageNumber, $this->stageCount, $this->bundled,
            array_values(array_unique([...$this->hidden, ...$this->bundled])), $this->position, true,
        );
    }

    /** The reference the board uses for this card (BoardOrder). */
    public function ref(): string
    {
        return $this->kind.':'.$this->id;
    }

    public function isProject(): bool
    {
        return $this->kind === 'project';
    }

    /** The page this card opens. */
    public function url(): string
    {
        return $this->isProject() ? route('shell.project', $this->id) : route('shell.group', $this->id);
    }

    public function percent(): int
    {
        if ($this->progress === null || $this->progress['total'] === 0) {
            return 0;
        }

        return (int) round(100 * $this->progress['done'] / $this->progress['total']);
    }
}
