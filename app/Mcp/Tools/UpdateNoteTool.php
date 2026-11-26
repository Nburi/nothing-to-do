<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\NoteEditor;
use Illuminate\Support\Facades\Validator;

class UpdateNoteTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_note';
    }

    public function description(): string
    {
        return 'Change a note card: its text (REPLACES the whole text — read it first to append; tags follow the text) and/or, for a project card, the stage it sits in (null = back to "Lose").';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'body' => ['type' => 'string', 'description' => 'Markdown, 1–'.NoteEditor::BODY_MAX.' characters.'],
                'stage_id' => ['type' => ['integer', 'null'], 'description' => 'Projects only. null = "Lose".'],
            ],
            'required' => ['id'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $note = $this->ownedNote($user, $arguments['id'] ?? null);
        $data = Validator::make($arguments, [
            'body' => ['sometimes', 'required', 'string', 'max:'.NoteEditor::BODY_MAX],
            'stage_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $editor = app(NoteEditor::class);
        if (isset($data['body'])) {
            $editor->update($note, trim($data['body']));
        }
        if (array_key_exists('stage_id', $data)) {
            $stage = $data['stage_id'] === null ? null : $this->ownedStage($user, $data['stage_id']);
            $this->guarded(fn () => $editor->setStage($note, $stage));
        }

        return ProjectsPayload::note($note->refresh()->load('tags'));
    }
}
