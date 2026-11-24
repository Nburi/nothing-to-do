<?php

namespace App\Livewire\Concerns;

use App\Models\ProjectStage;
use App\Services\Projects\ProjectEditor;
use App\Services\Projects\StepMove;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Step 4.3c: moving a step on a project or group page. Dragging, the keyboard and the ⋯ menu all end
 * in moveStep(); the toast it leaves has "Rückgängig" (kind "move" of the page's `$undo`).
 *
 * The page provides step(), stageForKey(), guard(), gone() and say(), a public ?string $notice and a
 * public ?array $undo. A stage is named by its key: "none" or "stage-ID" (the same keys the page's
 * lanes carry in `data-lane`).
 */
trait ManagesStepMoves
{
    /** @throws ModelNotFoundException for a key that is no stage of this page */
    abstract protected function stageForKey(string $key): ?ProjectStage;

    /**
     * The step goes to the stage `$to` at `$position` among that stage's open steps (counted without
     * the step itself, null = the end). Nothing happens, and nothing is said, when it sits there already.
     */
    public function moveStep(int $id, string $to, ?int $position = null): void
    {
        $this->guard(function () use ($id, $to, $position) {
            $step = $this->step($id);
            $move = app(ProjectEditor::class)->place($step, $this->stageForKey($to), $position);

            if (! $move->changed) {
                return;
            }

            $this->undo = [
                'kind' => 'move',
                'id' => $step->id,
                'stage' => $move->fromStageId,
                'index' => $move->fromIndex,
                'cleared' => $move->cleared,
            ];
            $this->notice = $this->moveNotice(Str::limit($step->title, 60), $move);
        });
    }

    /** Called by the page's undoLast() for kind "move". */
    protected function undoMove(array $undo): void
    {
        try {
            $step = $this->step((int) $undo['id']);
            $stage = $undo['stage'] === null ? null : $this->stageForKey('stage-'.$undo['stage']);
            app(ProjectEditor::class)->putBack($step, $stage, (int) $undo['index'], (array) $undo['cleared']);
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->say(__('projects.move.undone'));
    }

    private function moveNotice(string $title, StepMove $move): string
    {
        $text = match (true) {
            ! $move->stageChanged() => __('projects.move.placed', ['title' => $title, 'place' => $move->toIndex + 1]),
            $move->toStageId === null => __('projects.move.no_stage', ['title' => $title]),
            default => __('projects.move.in_stage', ['title' => $title, 'number' => $move->toStageNumber]),
        };

        return $move->cleared === [] ? $text : $text.' '.__('projects.move.waits_cleared');
    }
}
