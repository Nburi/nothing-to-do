<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesProjectModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Services\Projects\ProjectEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Steps 4.3 and 4.3c: where a step of a project or group sits, and what it waits on. */
class StepController extends Controller
{
    use ResolvesProjectModels;

    /**
     * One step to a place: a stage of its project (stage_id null = no stage; a group has none) and a
     * position among that stage's open steps (index from 0, default the end). Reordering and moving to
     * another stage are the same call; the same place again changes nothing (move.changed = false).
     */
    public function place(Request $request, int $task, ProjectEditor $editor): JsonResponse
    {
        $step = $request->user()->tasks()->findOrFail($task);
        $data = $request->validate([
            'stage_id' => ['nullable', 'integer'],
            'index' => ['nullable', 'integer', 'min:0'],
        ]);

        $stage = $this->stageOrNull($request->user(), $data['stage_id'] ?? null);
        $move = $this->editing('stage_id', fn () => $editor->place($step, $stage, $data['index'] ?? null));

        return response()->json(['data' => [
            'step' => (new TaskResource($step->refresh()))->resolve(),
            'move' => [
                'changed' => $move->changed,
                'from_stage_id' => $move->fromStageId,
                'from_index' => $move->fromIndex,
                'to_stage_id' => $move->toStageId,
                'to_index' => $move->toIndex,
                'cleared_waits_on' => $move->cleared,
            ],
        ]]);
    }

    /** "Wartet auf": the step waits on another step of its project (waits_on_id null = on nothing). */
    public function waitsOn(Request $request, int $task, ProjectEditor $editor): JsonResponse
    {
        $step = $request->user()->tasks()->findOrFail($task);
        $data = $request->validate(['waits_on_id' => ['present', 'nullable', 'integer']]);

        $on = $data['waits_on_id'] === null ? null : $request->user()->tasks()->findOrFail($data['waits_on_id']);
        $this->editing('waits_on_id', fn () => $editor->setWaitsOn($step, $on));

        return (new TaskResource($step->refresh()))->response();
    }
}
