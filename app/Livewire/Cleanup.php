<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Task;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Full-screen swipe-stack triage: sort the Inbox into To-Dos/Tasks, then pass
 * over every open To-Do/Task to flag Today, add a deadline, and mark it
 * important. Ordering, phase, and the session-local "später" queue live
 * entirely client-side (see the `cleanup` Alpine store in app.js) — these
 * computed properties are only ever the source of truth for task content,
 * re-read fresh from the database on every request.
 */
#[Layout('layouts.app')]
class Cleanup extends Component
{
    use ManagesTasks;

    #[Computed]
    public function inboxQueue(): Collection
    {
        return auth()->user()->tasks()
            ->onBoard()
            ->active()
            ->inList('inbox')
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function reviewQueue(): Collection
    {
        return auth()->user()->tasks()
            ->onBoard()
            ->active()
            ->whereIn('list', Task::TODAY_LISTS)
            ->boardOrdered()
            ->get();
    }

    /** Inbox triage: file a task into To-Dos or Tasks. */
    public function assignList(int $id, string $list): void
    {
        if (! in_array($list, ['todos', 'tasks'], true)) {
            return;
        }

        $this->userTask($id)->update(['list' => $list]);
    }

    /** Review pass: flag a task for today. */
    public function markToday(int $id): void
    {
        $task = $this->userTask($id);

        if (! in_array($task->list, Task::TODAY_LISTS, true)) {
            return;
        }

        $task->update(['is_today' => true]);
    }

    public function render()
    {
        return view('livewire.cleanup');
    }
}
