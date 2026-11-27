<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Planning\CrunchDecisions;
use Illuminate\Support\Facades\Validator;

/** The crunch screen's "Verschieben" (api parity): the same rules as the app, through CrunchDecisions. */
class MoveTaskTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'move_task';
    }

    public function description(): string
    {
        return 'Move a task\'s deadline later, like "Verschieben" on the crunch screen. With date (YYYY-MM-DD) the HARD deadline moves to that day: it has to lie after the current one, and a hard deadline is a commitment, so ask the user first. Without date the SOFT deadline moves one week on (or is dropped when the hard deadline comes first). Fails when the task has no deadline of that kind.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'New hard deadline. Leave out to move the soft deadline.'],
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
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $task = $this->ownedTask($user, $arguments['id'] ?? null);
        $data = Validator::make($arguments, ['date' => ['sometimes', 'nullable', 'date_format:Y-m-d']])->validate();
        $decisions = app(CrunchDecisions::class);

        $moved = isset($data['date'])
            ? $decisions->moveHard($user, $task->id, $data['date'])
            : $decisions->moveSoft($user, $task->id);

        return (new TaskResource($moved->refresh()))->resolve();
    }
}
