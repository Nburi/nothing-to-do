<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\NoteEditor;
use Illuminate\Support\Facades\Validator;

class AddNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'add_note';
    }

    public function description(): string
    {
        return 'Add a note card (Markdown) to a project or a task group. Give exactly one of project_id or group_id. #tags in the text become shared tags. A new card lands in "Lose" (no stage); a project card can be sorted into a stage with stage_id. A card can become a step in the app.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'],
                'group_id' => ['type' => 'integer'],
                'body' => ['type' => 'string', 'description' => 'Markdown, 1–'.NoteEditor::BODY_MAX.' characters.'],
                'stage_id' => ['type' => ['integer', 'null'], 'description' => 'Projects only: a stage of this project.'],
            ],
            'required' => ['body'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'project_id' => ['required_without:group_id', 'prohibits:group_id', 'integer'],
            'group_id' => ['required_without:project_id', 'prohibits:project_id', 'integer'],
            'body' => ['required', 'string', 'max:'.NoteEditor::BODY_MAX],
            'stage_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $owner = isset($data['project_id'])
            ? $this->ownedProject($user, $data['project_id'])
            : $this->ownedTaskGroup($user, $data['group_id']);
        $stage = isset($data['stage_id']) ? $this->ownedStage($user, $data['stage_id']) : null;

        $note = $this->guarded(fn () => app(NoteEditor::class)->add($user, $owner, trim($data['body']), $stage));

        return ProjectsPayload::note($note);
    }
}
