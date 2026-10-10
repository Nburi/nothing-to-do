<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** The board's "Überfällig retten" banner as a tool: only SOFT dates (due_date), never a hard deadline. */
class RescheduleOverdueTasksTool extends McpTool
{
    public function name(): string
    {
        return 'reschedule_overdue_tasks';
    }

    public function description(): string
    {
        return 'Move every open board task whose SELF-IMPOSED date (due_date, no hard deadline) has passed to a new '
            .'date in one go — the "rescue overdue" action. Hard deadlines are never touched. when = today | tomorrow | '
            .'next_monday | clear (remove the date) | an explicit date (YYYY-MM-DD, not in the past). Returns the '
            .'moved tasks with their previous dates so it can be undone with update_tasks.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['when' => ['type' => 'string', 'description' => 'today | tomorrow | next_monday | clear | YYYY-MM-DD']],
            'required' => ['when'],
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
        $data = Validator::make($arguments, ['when' => ['required', 'string']])->validate();
        $today = $user->localToday();

        $target = match ($data['when']) {
            'today' => $today->copy(),
            'tomorrow' => $today->copy()->addDay(),
            'next_monday' => $today->copy()->next(Carbon::MONDAY),
            'clear' => null,
            default => self::explicitDate($data['when'], $today),
        };

        $tasks = Task::query()->forUser($user)->active()->onBoard()
            ->whereNull('deadline')->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('due_date')
            ->get(['id', 'title', 'due_date']);

        if ($tasks->isNotEmpty()) {
            Task::query()->forUser($user)->whereKey($tasks->pluck('id'))->update(['due_date' => $target?->toDateString()]);
        }

        return [
            'moved' => $tasks->count(),
            'new_date' => $target?->toDateString(),
            'tasks' => $tasks->map(fn (Task $t) => ['id' => $t->id, 'title' => $t->title, 'previous_due_date' => $t->due_date->toDateString()])->values()->all(),
        ];
    }

    private static function explicitDate(string $value, Carbon $today): Carbon
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw ValidationException::withMessages(['when' => 'when must be today, tomorrow, next_monday, clear or a real date (YYYY-MM-DD).']);
        }

        $day = Carbon::instance($date)->startOfDay();

        if ($day->lt($today)) {
            throw ValidationException::withMessages(['when' => 'The new date must not be in the past.']);
        }

        return $day;
    }
}
