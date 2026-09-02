<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
use App\Services\ListConcepts;
use Illuminate\Support\Collection;

/**
 * A simplified, concept-aware projection of the board — read tasks the same
 * way the user currently sees them, whichever of the four list concepts
 * (App\Services\ListConcepts) is active. This is deliberately NOT a
 * pixel-perfect mirror of TaskBoard's own rendering (grouped-task box
 * pinning, Notfallmodus overlays, homework-preview merging are all out of
 * scope) — it reproduces the one thing that actually differs per concept:
 * how active board tasks are bucketed. Use list_tasks for a flat, filtered
 * read regardless of concept.
 */
class GetBoardTool extends McpTool
{
    public function name(): string
    {
        return 'get_board';
    }

    public function description(): string
    {
        return 'Read the board the way this user currently sees it, bucketed according to their active '
            .'list concept (3 Things / Simple / Eisenhower-Matrix / Kanban). Call get_settings first if '
            .'you need to know which concept is active before deciding how to interpret the buckets.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $concept = ListConcepts::for($user);

        return [
            'list_concept' => $concept,
            'columns' => match ($concept) {
                'simple' => $this->simple($user),
                'eisenhower' => $this->eisenhower($user),
                'kanban' => $this->kanban($user),
                default => $this->threeThings($user),
            },
        ];
    }

    private function baseActive(User $user)
    {
        return $user->tasks()->onBoard()->active();
    }

    private function resolve(Collection $tasks): array
    {
        return TaskResource::collection($tasks)->resolve();
    }

    private function threeThings(User $user): array
    {
        return collect(Task::BOARD_LISTS)->mapWithKeys(fn (string $list) => [
            $list => $this->resolve($this->baseActive($user)->clone()->inList($list)->boardOrdered()->get()),
        ])->all();
    }

    private function simple(User $user): array
    {
        $all = $this->baseActive($user)->boardOrdered()->get();

        return [
            'today' => $this->resolve($all->where('is_today', true)->values()),
            'other' => $this->resolve($all->where('is_today', false)->values()),
        ];
    }

    private function eisenhower(User $user): array
    {
        $all = $this->baseActive($user)->boardOrdered()->get();

        $quadrant = fn (bool $important, bool $urgent) => $all
            ->filter(fn (Task $t) => $t->is_important === $important && $t->isUrgent() === $urgent)
            ->values();

        return [
            'important_urgent' => $this->resolve($quadrant(true, true)),
            'important_not_urgent' => $this->resolve($quadrant(true, false)),
            'not_important_urgent' => $this->resolve($quadrant(false, true)),
            'not_important_not_urgent' => $this->resolve($quadrant(false, false)),
        ];
    }

    private function kanban(User $user): array
    {
        $active = $this->baseActive($user)->boardOrdered()->get();
        $done = $user->tasks()->onBoard()->where('is_completed', true)
            ->orderByDesc('completed_at')->limit(50)->get();

        return [
            'backlog' => $this->resolve($active->where('is_today', false)->values()),
            'in_arbeit' => $this->resolve($active->where('is_today', true)->values()),
            'erledigt' => $this->resolve($done),
        ];
    }
}
