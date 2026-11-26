<?php

namespace App\Http\Resources;

use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Projects\Bundle;
use App\Services\Projects\NextSteps;
use Illuminate\Database\Eloquent\Builder;

/**
 * JSON shapes of projects, groups, stages and note cards (steps 4.3, 4.3b), shared by the REST API and
 * the MCP tools. A project and a group both come as a "bundle": what the board card shows (the next
 * two steps, progress, current stage); the detail adds every step, the stages and the note cards.
 */
final class ProjectsPayload
{
    /** @return array<string, mixed> */
    public static function bundle(Bundle $b): array
    {
        return [
            'id' => $b->id,
            'kind' => $b->kind,
            'name' => $b->name,
            // The board column the card sits in; null = nothing to do right now, so no card on the board.
            'list' => $b->list,
            'open' => $b->open,
            'more' => $b->more,
            // Project: the current stage only (null without stages). Group: the whole group.
            'progress' => $b->progress,
            'current_stage' => $b->stage === null ? null : self::stage($b->stage) + ['number' => $b->stageNumber],
            'stage_count' => $b->stageCount,
            'next_steps' => TaskResource::collection($b->visible)->resolve(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function bundles(User $user, string $kind): array
    {
        return app(NextSteps::class)->bundles($user)
            ->filter(fn (Bundle $b) => $b->kind === $kind)
            ->map(fn (Bundle $b) => self::bundle($b))
            ->values()->all();
    }

    /** @return array<string, mixed> */
    public static function project(User $user, Project $project): array
    {
        $stages = $project->stages()->get();
        $rank = $stages->pluck('id')->flip();

        $steps = Task::forUser($user)->where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get()
            // Stage by stage as on the roadmap, steps without a stage last; each stage in its own order.
            ->sortBy(fn (Task $t) => $rank->get($t->stage_id, PHP_INT_MAX))->values();

        $counts = $steps->groupBy('stage_id')->map(fn ($g) => [
            'open' => $g->where('is_completed', false)->count(),
            'done' => $g->where('is_completed', true)->count(),
        ]);

        return self::bundle(self::bundleOf($user, 'project', $project->id)) + [
            'sort_order' => $project->sort_order,
            'stages' => $stages->map(fn (ProjectStage $s) => self::stage($s) + [
                'open' => $counts[$s->id]['open'] ?? 0,
                'done' => $counts[$s->id]['done'] ?? 0,
            ])->all(),
            'steps' => TaskResource::collection($steps)->resolve(),
            'notes' => self::notes(Note::where('user_id', $user->id)->where('project_id', $project->id)),
            'created_at' => $project->created_at?->toIso8601String(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function group(User $user, TaskGroup $group): array
    {
        $steps = Task::forUser($user)->whereNull('project_id')->where('group_id', $group->id)->orderBy('sort_order')->orderBy('id')->get();

        return self::bundle(self::bundleOf($user, 'group', $group->id)) + [
            'sort_order' => $group->sort_order,
            'steps' => TaskResource::collection($steps)->resolve(),
            'notes' => self::notes(Note::where('user_id', $user->id)->where('group_id', $group->id)),
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function stage(ProjectStage $stage): array
    {
        return [
            'id' => $stage->id,
            'project_id' => $stage->project_id,
            'name' => $stage->name,
            'target_date' => $stage->target_date?->toDateString(),
            'sort_order' => $stage->sort_order,
        ];
    }

    /** @return array<string, mixed> */
    public static function note(Note $note): array
    {
        return [
            'id' => $note->id,
            'project_id' => $note->project_id,
            'group_id' => $note->group_id,
            // Null = "Lose" (not sorted into a stage yet; a group has no stages).
            'stage_id' => $note->stage_id,
            'body' => $note->body,
            'tags' => $note->tags->pluck('name')->all(),
            'sort_order' => $note->sort_order,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function notes(Builder $query): array
    {
        return $query->with('tags')->orderBy('sort_order')->orderBy('id')->get()->map(fn (Note $n) => self::note($n))->all();
    }

    private static function bundleOf(User $user, string $kind, int $id): Bundle
    {
        return app(NextSteps::class)->bundles($user)->first(fn (Bundle $b) => $b->kind === $kind && $b->id === $id);
    }
}
