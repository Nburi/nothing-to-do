<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesProjectModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectsPayload;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Services\Projects\ProjectEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Steps 4.3 and 4.3b through the API: projects with their stages (the roadmap) and steps. There is no
 * delete yet, as in the app itself (an empty project simply rests). Notes: NoteController.
 */
class ProjectController extends Controller
{
    use ResolvesProjectModels;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ProjectsPayload::bundles($request->user(), 'project')]);
    }

    public function store(Request $request, ProjectEditor $editor): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:'.Project::NAME_MAX]]);
        $project = $editor->createProject($request->user(), trim($data['name']));

        return response()->json(['data' => ProjectsPayload::project($request->user(), $project)], 201);
    }

    public function show(Request $request, int $project): JsonResponse
    {
        return response()->json(['data' => ProjectsPayload::project($request->user(), $this->ownedProject($request->user(), $project))]);
    }

    public function update(Request $request, int $project, ProjectEditor $editor): JsonResponse
    {
        $model = $this->ownedProject($request->user(), $project);
        $data = $request->validate(['name' => ['required', 'string', 'max:'.Project::NAME_MAX]]);
        $editor->rename($model, trim($data['name']));

        return response()->json(['data' => ProjectsPayload::project($request->user(), $model->refresh())]);
    }

    public function storeStage(Request $request, int $project, ProjectEditor $editor): JsonResponse
    {
        $model = $this->ownedProject($request->user(), $project);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.ProjectStage::NAME_MAX],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $stage = $editor->addStage($model, trim($data['name']), $data['target_date'] ?? null);

        return response()->json(['data' => ProjectsPayload::stage($stage->refresh())], 201);
    }

    public function updateStage(Request $request, int $stage, ProjectEditor $editor): JsonResponse
    {
        $model = $this->ownedStage($request->user(), $stage);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:'.ProjectStage::NAME_MAX],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            // Place in the roadmap, 0 = first (clamped). A "waits on" link the move contradicts is let go.
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $editor->updateStage(
            $model,
            isset($data['name']) ? trim($data['name']) : $model->name,
            array_key_exists('target_date', $data) ? $data['target_date'] : $model->target_date?->toDateString(),
        );

        $payload = ['data' => ProjectsPayload::stage($model->refresh())];
        if (isset($data['position'])) {
            $move = $editor->moveStage($model, (int) $data['position']);
            $payload = ['data' => ProjectsPayload::stage($model->refresh()), 'move' => [
                'changed' => $move->changed,
                'from_index' => $move->fromIndex,
                'to_index' => $move->toIndex,
                'cleared_waits_on' => $move->cleared,
            ]];
        }

        return response()->json($payload);
    }

    /**
     * Delete the project. No step is lost: every task of it stays on the board in its own list without
     * project, stage or "waits on" link; its stages and note cards go (ProjectEditor::deleteProject()).
     */
    public function destroy(Request $request, int $project, ProjectEditor $editor): JsonResponse
    {
        $editor->deleteProject($this->ownedProject($request->user(), $project));

        return response()->json(null, 204);
    }

    /** The stage goes, its steps stay in the project without a stage, its note cards go back to "Lose". */
    public function destroyStage(Request $request, int $stage, ProjectEditor $editor): JsonResponse
    {
        $editor->removeStage($this->ownedStage($request->user(), $stage));

        return response()->json(null, 204);
    }

    public function storeStep(Request $request, int $project, ProjectEditor $editor): JsonResponse
    {
        $model = $this->ownedProject($request->user(), $project);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'stage_id' => ['nullable', 'integer'],
            'list' => ['sometimes', Rule::in(Task::WORK_LISTS)],
        ]);

        $stage = $this->stageOrNull($request->user(), $data['stage_id'] ?? null);
        $step = $this->editing('stage_id', fn () => $editor->addProjectStep($request->user(), $model, $stage, trim($data['title']), $data['list'] ?? 'tasks'));

        return (new TaskResource($step->refresh()))->response()->setStatusCode(201);
    }
}
