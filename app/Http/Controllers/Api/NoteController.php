<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesProjectModels;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectsPayload;
use App\Services\Projects\NoteEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Step 4.3b through the API: note cards of projects and groups, with their shared #tags. */
class NoteController extends Controller
{
    use ResolvesProjectModels;

    public function storeForProject(Request $request, int $project, NoteEditor $editor): JsonResponse
    {
        $model = $this->ownedProject($request->user(), $project);
        $data = $this->validated($request, withStage: true);
        $stage = $this->stageOrNull($request->user(), $data['stage_id'] ?? null);

        $note = $this->editing('stage_id', fn () => $editor->add($request->user(), $model, trim($data['body']), $stage));

        return response()->json(['data' => ProjectsPayload::note($note)], 201);
    }

    public function storeForGroup(Request $request, int $group, NoteEditor $editor): JsonResponse
    {
        $model = $this->ownedGroup($request->user(), $group);
        $data = $this->validated($request, withStage: false);

        $note = $editor->add($request->user(), $model, trim($data['body']));

        return response()->json(['data' => ProjectsPayload::note($note)], 201);
    }

    /** body replaces the whole text; stage_id (projects only) sorts the card into a stage, null = back to "Lose". */
    public function update(Request $request, int $note, NoteEditor $editor): JsonResponse
    {
        $model = $this->ownedNote($request->user(), $note);
        $data = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:'.NoteEditor::BODY_MAX],
            'stage_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        if (isset($data['body'])) {
            $editor->update($model, trim($data['body']));
        }
        if (array_key_exists('stage_id', $data)) {
            $stage = $this->stageOrNull($request->user(), $data['stage_id']);
            $this->editing('stage_id', fn () => $editor->setStage($model, $stage));
        }

        return response()->json(['data' => ProjectsPayload::note($model->refresh()->load('tags'))]);
    }

    public function destroy(Request $request, int $note, NoteEditor $editor): JsonResponse
    {
        $editor->delete($this->ownedNote($request->user(), $note));

        return response()->json(null, 204);
    }

    /** @return array{body: string, stage_id?: ?int} */
    private function validated(Request $request, bool $withStage): array
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:'.NoteEditor::BODY_MAX],
            ...($withStage ? ['stage_id' => ['nullable', 'integer']] : []),
        ]);
    }
}
