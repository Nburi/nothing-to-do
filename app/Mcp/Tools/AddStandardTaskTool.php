<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Models\AgendaEntry;
use App\Models\User;
use App\Services\AppModules;
use App\Services\DayPlanner;
use App\Services\PlannerStandardTasks;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AddStandardTaskTool extends McpTool
{
    use PlannerToolDefaults;

    public function name(): string
    {
        return 'add_standard_task';
    }

    public function description(): string
    {
        return 'Create one of the Planer\'s two recurring quick tasks and place it on a day: "todos_clear" '
            .'("ToDos erledigen", a fixed slot for open To-Dos) or "study" ("Lernen"; study_mode general | subject | '
            .'exam — for subject/exam pass a subject, for exam optionally an open exam entry via agenda_entry_id, '
            .'whose date becomes the deadline). duration_minutes is the length of this block (5–480, default 25). '
            .'The result is an ordinary task.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'template' => ['type' => 'string', 'enum' => array_keys(PlannerStandardTasks::CATALOG)],
                'date' => ['type' => 'string', 'format' => 'date'],
                'duration_minutes' => ['type' => 'integer', 'minimum' => PlannerStandardTasks::MIN_DURATION, 'maximum' => PlannerStandardTasks::MAX_DURATION],
                'study_mode' => ['type' => 'string', 'enum' => array_keys(PlannerStandardTasks::STUDY_MODES), 'description' => 'Only for template "study"; default general.'],
                'subject' => ['type' => 'string', 'description' => 'Required for study_mode subject/exam, max 80.'],
                'agenda_entry_id' => ['type' => 'integer', 'description' => 'study_mode exam: an open exam entry (list_agenda_entries type=exam).'],
            ],
            'required' => ['template', 'date'],
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
            'template' => ['required', Rule::in(array_keys(PlannerStandardTasks::CATALOG))],
            'duration_minutes' => ['sometimes', 'integer', 'min:'.PlannerStandardTasks::MIN_DURATION, 'max:'.PlannerStandardTasks::MAX_DURATION],
            'study_mode' => ['sometimes', Rule::in(array_keys(PlannerStandardTasks::STUDY_MODES))],
            'subject' => ['sometimes', 'nullable', 'string', 'max:80'],
            'agenda_entry_id' => ['sometimes', 'integer'],
        ])->validate();

        $date = self::horizonDate($user, $arguments['date'] ?? null);
        $duration = $data['duration_minutes'] ?? PlannerStandardTasks::DEFAULT_DURATION;
        $deadline = null;

        if ($data['template'] === 'todos_clear') {
            $title = PlannerStandardTasks::label('todos_clear');
        } else {
            $mode = $data['study_mode'] ?? 'general';
            $subject = trim((string) ($data['subject'] ?? ''));

            if ($mode !== 'general' && $subject === '') {
                throw new McpToolExecutionException('subject is required for study_mode "'.$mode.'".');
            }

            if ($mode === 'exam' && isset($data['agenda_entry_id'])) {
                $exam = AppModules::isVisible($user, 'agenda')
                    ? AgendaEntry::visibleTo($user)->ofType('exam')->openFor($user)->find($data['agenda_entry_id'])
                    : null;

                if ($exam === null) {
                    throw new McpToolExecutionException("No open exam entry with id {$data['agenda_entry_id']} found for this account.");
                }

                $deadline = $exam->date;
            }

            $title = PlannerStandardTasks::studyTitle($mode, $mode === 'general' ? null : $subject);
        }

        $task = $user->tasks()->create([
            'title' => $title,
            'list' => PlannerStandardTasks::listFor($data['template']),
            'duration_minutes' => $duration,
            'deadline' => $deadline,
            'sort_order' => 0,
        ]);

        DayPlanner::moveToDay($user, "task:{$task->id}", $date);

        return ['created' => true, 'task_id' => $task->id, 'title' => $title, 'duration_minutes' => $duration, 'date' => $date];
    }
}
