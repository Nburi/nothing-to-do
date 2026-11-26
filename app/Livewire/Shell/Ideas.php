<?php

namespace App\Livewire\Shell;

use App\Models\Task;
use App\Models\User;
use App\Planning\Session\Phase;
use App\Services\FocusSession;
use App\Services\Planning\FreeTimeIdeas;
use App\Support\TaskMutator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Ideen" (refactor step 3.4): the browse view of Later, as cards (Niels' option A, 2026-11-11).
 * The old Bastelideen live on here: title, area, size and "Starte mit" on every card.
 * Reached from the Werkbank top bar ("Ideen") and the Stapel bottom tab ("Später").
 *
 * Two actions, both from the shell's existing patterns: "Jetzt machen" starts a session with the
 * idea (like Start on the board, then the board shows the session), "Erledigt" finishes it with
 * an undo instead of a confirm (CONVENTIONS 7). Adding ideas is the global capture line.
 */
#[Layout('layouts.shell')]
class Ideas extends Component
{
    /** Cards shown before "+ N more" (a 400-idea Later stays fast). */
    public const LIMIT = 120;

    /** A calm one-line message after an action (never an alert). */
    public ?string $notice = null;

    /** The idea last marked done, for "Rückgängig". */
    #[Locked]
    public ?int $undoId = null;

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /** @return Collection<int, Task> */
    #[Computed]
    public function ideas(): Collection
    {
        return Task::forUser($this->user)->active()->where('list', Task::LATER)
            ->with('area')
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /** Tonight's free-time idea, marked on its card. */
    #[Computed]
    public function offeredId(): ?int
    {
        $offer = app(FreeTimeIdeas::class)->current($this->user, CarbonImmutable::now());

        return $offer->hasIdea() ? $offer->task->id : null;
    }

    /** A work phase runs: the engine does not switch tasks inside it (as on the board). */
    #[Computed]
    public function locked(): bool
    {
        return app(FocusSession::class)->current($this->user, CarbonImmutable::now())->phase === Phase::Work;
    }

    /** "Jetzt machen": start a session with this idea and show it on the board. */
    public function doNow(int $id): void
    {
        $this->notice = null;

        if ($this->locked) {
            $this->notice = __('shell.ideas.not_now');

            return;
        }

        try {
            $task = $this->idea($id);
            app(FocusSession::class)->start($this->user, $task->id, CarbonImmutable::now());
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->redirectRoute('shell.board', navigate: true);
    }

    /** "Erledigt", undoable (CONVENTIONS 7). */
    public function done(int $id): void
    {
        $this->notice = null;

        try {
            $task = TaskMutator::applyUpdate($this->idea($id), $this->user, ['is_completed' => true]);
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->undoId = $task->id;
        $this->notice = __('shell.ideas.done_notice', ['title' => Str::limit($task->title, 60)]);
        $this->fresh();
    }

    public function undoDone(): void
    {
        $task = $this->undoId === null ? null : Task::forUser($this->user)->where('list', Task::LATER)->whereKey($this->undoId)->first();
        $this->undoId = null;

        if ($task === null) {
            $this->gone();

            return;
        }

        TaskMutator::applyUpdate($task, $this->user, ['is_completed' => false]);
        $this->notice = __('shell.ideas.undone');
        $this->fresh();
    }

    /** An idea was edited, moved or deleted (step 4.9): read Later again. */
    #[On('items-changed')]
    public function itemsChanged(): void
    {
        $this->fresh();
    }

    public function dismiss(): void
    {
        $this->notice = null;
        $this->undoId = null;
    }

    private function idea(int $id): Task
    {
        return Task::forUser($this->user)->active()->where('list', Task::LATER)->findOrFail($id);
    }

    private function gone(): void
    {
        $this->undoId = null;
        $this->notice = __('shell.ideas.gone');
        $this->fresh();
    }

    private function fresh(): void
    {
        foreach (['ideas', 'offeredId', 'locked'] as $computed) {
            unset($this->{$computed});
        }
    }

    public function render(): View
    {
        return view('livewire.shell.ideas')->title(__('shell.ideas.title').' · nothing-to-do');
    }
}
