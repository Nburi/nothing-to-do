<?php

namespace App\Livewire\Concerns;

use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Tag;
use App\Models\TaskGroup;
use App\Services\Projects\NoteEditor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Step 4.3b: the note cards of a project or group page. Quick note (Enter saves), edit in place,
 * filter by #tag, move to a stage (chip sheet, or drag on desktop), "→ Schritt", delete. Delete and
 * "→ Schritt" are undoable through the page's `$undo` / undoLast() (kind "note").
 *
 * The page provides noteOwner(), guard(), gone() and fresh(), and a public array $undo.
 */
trait ManagesNotes
{
    public string $noteDraft = '';

    public ?string $noteTag = null;

    #[Locked]
    public ?int $editingNote = null;

    public string $editNoteBody = '';

    abstract protected function noteOwner(): Project|TaskGroup;

    /** @return Collection<int, Note> in pile order (no stage first, then stages), each pile by sort_order */
    #[Computed]
    public function notes(): Collection
    {
        $owner = $this->noteOwner();

        return Note::where('user_id', $this->user->id)
            ->where($owner instanceof Project ? 'project_id' : 'group_id', $owner->id)
            ->with('tags')->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return list<string> the user's tags, for the # suggestions (shared across projects) */
    #[Computed]
    public function userTags(): array
    {
        return Tag::where('user_id', $this->user->id)->orderBy('name')->pluck('name')->all();
    }

    public function addNote(): void
    {
        $this->validate(['noteDraft' => ['required', 'string', 'max:'.NoteEditor::BODY_MAX]], [
            'noteDraft.required' => __('projects.notes.required'),
            'noteDraft.max' => __('projects.notes.too_long', ['max' => NoteEditor::BODY_MAX]),
        ]);

        app(NoteEditor::class)->add($this->user, $this->noteOwner(), trim($this->noteDraft));
        $this->noteDraft = '';
        $this->freshNotes();
    }

    public function editNote(int $id): void
    {
        $this->guard(function () use ($id) {
            $note = $this->note($id);
            $this->editingNote = $note->id;
            $this->editNoteBody = $note->body;
            $this->resetValidation();
        });
    }

    public function saveNote(): void
    {
        $this->validate(['editNoteBody' => ['required', 'string', 'max:'.NoteEditor::BODY_MAX]], [
            'editNoteBody.required' => __('projects.notes.required'),
            'editNoteBody.max' => __('projects.notes.too_long', ['max' => NoteEditor::BODY_MAX]),
        ]);

        $this->guard(function () {
            app(NoteEditor::class)->update($this->note($this->editingNote), trim($this->editNoteBody));
            $this->editingNote = null;
        });
        $this->freshNotes();
    }

    public function cancelNote(): void
    {
        $this->editingNote = null;
        $this->resetValidation();
    }

    public function filterTag(?string $name): void
    {
        $this->noteTag = $name === null || $name === '' || $name === $this->noteTag ? null : mb_strtolower($name);
    }

    /** The stage chip and the drop on a pile: "" or "none" = Lose, else a stage id. */
    public function setNoteStage(int $id, string $key, ?int $index = null): void
    {
        $this->guard(function () use ($id, $key, $index) {
            $note = $this->note($id);
            $stage = in_array($key, ['', 'none'], true) ? null : $this->noteStage((int) Str::after($key, 'stage-'));

            try {
                $index === null ? app(NoteEditor::class)->setStage($note, $stage) : app(NoteEditor::class)->place($note, $stage, $index);
            } catch (InvalidArgumentException) {
                $this->gone();
            }
        });
        $this->freshNotes();
    }

    public function deleteNote(int $id): void
    {
        $this->guard(function () use ($id) {
            $note = $this->note($id);
            $editor = app(NoteEditor::class);
            $this->undo = ['kind' => 'note', 'snap' => $editor->snapshot($note), 'task' => null];
            $editor->delete($note);
            $this->notice = __('projects.notes.deleted');
        });
        $this->freshNotes();
    }

    /** "→ Schritt" (Niels, 2026-11-18: the note becomes the step). */
    public function noteToStep(int $id): void
    {
        $this->guard(function () use ($id) {
            $note = $this->note($id);
            $editor = app(NoteEditor::class);
            $snap = $editor->snapshot($note);
            $task = $editor->toStep($this->user, $note);
            $this->undo = ['kind' => 'note', 'snap' => $snap, 'task' => $task->id];
            $this->notice = __('projects.notes.to_step_done', ['title' => Str::limit($task->title, 60)]);
        });
        $this->freshNotes();
    }

    /** Called by the page's undoLast() for kind "note". */
    protected function undoNote(array $undo): void
    {
        app(NoteEditor::class)->restore($this->user, $undo['snap'], $undo['task']);
        $this->freshNotes();
    }

    private function note(?int $id): Note
    {
        $owner = $this->noteOwner();

        return Note::where('user_id', $this->user->id)
            ->where($owner instanceof Project ? 'project_id' : 'group_id', $owner->id)
            ->findOrFail($id);
    }

    private function noteStage(int $id): ProjectStage
    {
        $owner = $this->noteOwner();
        if (! $owner instanceof Project) {
            throw new ModelNotFoundException;
        }

        return ProjectStage::where('project_id', $owner->id)->findOrFail($id);
    }

    private function freshNotes(): void
    {
        unset($this->notes, $this->userTags);
        $this->fresh();
    }
}
