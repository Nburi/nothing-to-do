<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ArrangeEmergencyTasksTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'arrange_emergency_tasks';
    }

    public function description(): string
    {
        return 'Set the order in which a project\'s open tasks will be done (the project\'s own task order — it '
            .'persists after the mode ends) and, per task, the board column it surfaces under during Notfallmodus. '
            .'List the tasks in the order you want; each may carry list = inbox | todos | tasks. Only the listed tasks '
            .'are touched; ids outside the project are refused. Works for any project, active or not.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'],
                'tasks' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'list' => ['type' => 'string', 'enum' => Task::BOARD_LISTS],
                        ],
                        'required' => ['id'],
                    ],
                ],
            ],
            'required' => ['project_id', 'tasks'],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'emergency';
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
        $project = $this->ownedProject($user, $arguments['project_id'] ?? null);

        $data = Validator::make($arguments, [
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*.id' => ['required', 'integer', 'distinct'],
            'tasks.*.list' => ['sometimes', Rule::in(Task::BOARD_LISTS)],
        ])->validate();

        $tasks = $project->tasks()->active()->whereIn('id', collect($data['tasks'])->pluck('id'))->get()->keyBy('id');

        foreach ($data['tasks'] as $row) {
            if (! $tasks->has($row['id'])) {
                throw new McpToolExecutionException("Task {$row['id']} is not an open task of project {$project->id}.");
            }
        }

        DB::transaction(function () use ($data, $tasks) {
            foreach (array_values($data['tasks']) as $position => $row) {
                $updates = ['sort_order' => $position];

                if (isset($row['list'])) {
                    $updates['emergency_list'] = $row['list'];
                }

                $tasks[$row['id']]->update($updates);
            }
        });

        return ['arranged' => count($data['tasks']), 'project_id' => $project->id];
    }
}
