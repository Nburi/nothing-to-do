<?php

namespace App\Services\Projects;

use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Step 4.3b: every change to note cards and their tags. A note belongs to exactly one project or
 * group of the same user; only a project note can sit in a stage (of that project). Tags follow
 * the text on every save; tags no note uses any more are removed, so the # suggestions stay clean.
 */
final class NoteEditor
{
    public const BODY_MAX = 10000;

    public function add(User $user, Project|TaskGroup $owner, string $body, ?ProjectStage $stage = null): Note
    {
        $this->assertStage($owner instanceof Project ? $owner->id : null, $stage);

        return DB::transaction(function () use ($user, $owner, $body, $stage) {
            $note = new Note(['body' => $body]);
            $note->forceFill([
                'user_id' => $user->id,
                'project_id' => $owner instanceof Project ? $owner->id : null,
                'group_id' => $owner instanceof TaskGroup ? $owner->id : null,
                'stage_id' => $stage?->id,
            ]);
            $note->sort_order = (int) $this->pile($note)->max('sort_order') + 1;
            $note->save();
            $this->syncTags($note);

            return $note->load('tags');
        });
    }

    public function update(Note $note, string $body): void
    {
        DB::transaction(function () use ($note, $body) {
            $note->update(['body' => $body]);
            $this->syncTags($note);
        });
    }

    /** Into a stage of its project (null = "Lose"), at the end there. */
    public function setStage(Note $note, ?ProjectStage $stage): void
    {
        $this->assertStage($note->project_id, $stage);

        $note->forceFill(['stage_id' => $stage?->id]);
        $note->sort_order = (int) $this->pile($note)->whereKeyNot($note->id)->max('sort_order') + 1;
        $note->save();
    }

    /** A drop on desktop: into the stage (null = "Lose") at position $index of that pile. */
    public function place(Note $note, ?ProjectStage $stage, int $index): void
    {
        $this->assertStage($note->project_id, $stage);

        DB::transaction(function () use ($note, $stage, $index) {
            $note->forceFill(['stage_id' => $stage?->id]);
            $ids = $this->pile($note)->whereKeyNot($note->id)->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
            array_splice($ids, max(0, min($index, count($ids))), 0, [$note->id]);
            $note->save();
            foreach ($ids as $order => $id) {
                Note::whereKey($id)->update(['sort_order' => $order]);
            }
        });
    }

    /** Everything needed to bring a deleted note back. */
    public function snapshot(Note $note): array
    {
        return $note->only(['project_id', 'group_id', 'stage_id', 'body', 'sort_order']);
    }

    public function delete(Note $note): void
    {
        DB::transaction(function () use ($note) {
            $userId = $note->user_id;
            $note->delete();
            $this->pruneTags($userId);
        });
    }

    /**
     * "→ Schritt": the note becomes a step of its project (in its stage) or group. The title is the
     * first line without Markdown and tags; the whole note stays on the step as its notes.
     */
    public function toStep(User $user, Note $note): Task
    {
        return DB::transaction(function () use ($user, $note) {
            $editor = app(ProjectEditor::class);
            $title = self::title($note->body);

            $task = $note->project_id !== null
                ? $editor->addProjectStep($user, $note->project()->firstOrFail(), $note->stage, $title)
                : $editor->addGroupStep($user, $note->group()->firstOrFail(), $title);

            $task->forceFill(['notes' => $note->body])->save();
            $this->delete($note);

            return $task;
        });
    }

    /** Undo of delete or "→ Schritt" (then the step it made goes, if nothing else changed it). */
    public function restore(User $user, array $snapshot, ?int $madeTaskId = null): Note
    {
        return DB::transaction(function () use ($user, $snapshot, $madeTaskId) {
            if ($madeTaskId !== null) {
                Task::where('user_id', $user->id)->whereKey($madeTaskId)->where('is_completed', false)->forceDelete();
            }

            $stageId = $snapshot['stage_id'] && ProjectStage::whereKey($snapshot['stage_id'])->where('project_id', $snapshot['project_id'])->exists()
                ? $snapshot['stage_id'] : null;

            $note = new Note(['body' => $snapshot['body'], 'sort_order' => $snapshot['sort_order']]);
            $note->forceFill([
                'user_id' => $user->id,
                'project_id' => $snapshot['project_id'],
                'group_id' => $snapshot['group_id'],
                'stage_id' => $stageId,
            ])->save();
            $this->syncTags($note);

            return $note->load('tags');
        });
    }

    /** First line without Markdown marks and tags, for a step title. */
    public static function title(string $body): string
    {
        $line = collect(preg_split('/\R/', $body))->map(fn ($l) => trim($l))->first(fn ($l) => $l !== '') ?? '';
        $line = preg_replace('/^(#{1,6}\s+|[-*+>]\s+|\d+[.)]\s+|\[[ xX]\]\s+)+/u', '', $line);
        $line = preg_replace('/\[([^\]]*)\]\([^)]*\)/u', '$1', $line);
        $line = preg_replace(NoteTags::PATTERN, '', $line);
        $line = trim(preg_replace('/\s+/u', ' ', str_replace(['**', '__', '++', '~~', '`', '*', '_'], '', $line)));

        return Str::limit($line !== '' ? $line : __('projects.notes.untitled'), 250, '…');
    }

    private function syncTags(Note $note): void
    {
        $ids = collect(NoteTags::parse($note->body))
            ->map(fn (string $name) => (Tag::where('user_id', $note->user_id)->where('name', $name)->first()
                ?? Tag::forceCreate(['user_id' => $note->user_id, 'name' => $name]))->id);

        $note->tags()->sync($ids->all());
        $this->pruneTags($note->user_id);
    }

    private function pruneTags(int $userId): void
    {
        Tag::where('user_id', $userId)->whereDoesntHave('notes')->delete();
    }

    /** Notes sharing an order with $note: same project and stage, or same group. */
    private function pile(Note $note)
    {
        $query = Note::where('user_id', $note->user_id);

        if ($note->project_id !== null) {
            return $query->where('project_id', $note->project_id)
                ->when($note->stage_id === null, fn ($q) => $q->whereNull('stage_id'), fn ($q) => $q->where('stage_id', $note->stage_id));
        }

        return $query->where('group_id', $note->group_id);
    }

    private function assertStage(?int $projectId, ?ProjectStage $stage): void
    {
        if ($stage !== null && ($projectId === null || $stage->project_id !== $projectId)) {
            throw new InvalidArgumentException('A note goes only into a stage of its own project.');
        }
    }
}
