<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Projects, groups, stages and notes always come through the owner (a foreign id is a 404, never a
 * write), and the editors' refusals become a 422 with one plain message.
 */
trait ResolvesProjectModels
{
    protected function ownedProject(User $user, int $id): Project
    {
        return Project::forUser($user)->findOrFail($id);
    }

    protected function ownedGroup(User $user, int $id): TaskGroup
    {
        return TaskGroup::forUser($user)->findOrFail($id);
    }

    protected function ownedStage(User $user, int $id): ProjectStage
    {
        return ProjectStage::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->findOrFail($id);
    }

    protected function ownedNote(User $user, int $id): Note
    {
        return Note::where('user_id', $user->id)->findOrFail($id);
    }

    /** An owned stage by id, or null for "no stage". */
    protected function stageOrNull(User $user, mixed $id): ?ProjectStage
    {
        return $id === null ? null : $this->ownedStage($user, (int) $id);
    }

    /** Runs an edit and turns an InvalidArgumentException of the editors into a 422 (a missing step stays a 404). */
    protected function editing(string $field, callable $edit): mixed
    {
        try {
            return $edit();
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage() === 'loop' ? 'These two steps would wait on each other.' : $e->getMessage()]);
        }
    }
}
