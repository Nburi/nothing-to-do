<?php

namespace App\Livewire\Shell;

use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\ItemEditor as ItemEditorService;
use App\Services\Planning\ItemLocked;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Editing, moving and deleting single items (refactor step 4.9, actions `edit`, `move`, `delete`).
 *
 * One component per page (the board and the Ideen page mount it), driven by events from the ⋯ menu
 * of an item row or card: `item-edit`, `item-move`, `item-delete`. The edit sheet reuses the fields
 * of the daily sort (CONVENTIONS 6: editing reuses the creation form). Every change leaves a toast
 * with "Rückgängig" instead of a confirm dialog (CONVENTIONS 7); a delete is a soft delete. The
 * writing and the rules live in App\Services\Planning\ItemEditor. When something changed it
 * dispatches `items-changed`, which the page listens for.
 */
class ItemEditor extends Component
{
    public bool $open = false;

    /** The item in the sheet. */
    #[Locked]
    public ?int $taskId = null;

    public string $title = '';

    public string $list = 'todos';

    public ?string $size = null;

    public ?string $hardDeadline = null;

    public ?string $softDeadline = null;

    public ?int $areaId = null;

    public ?string $firstStep = null;

    public ?string $notes = null;

    /** What the toast's "Rückgängig" takes back: ['kind' => 'edit'|'delete', 'id' => int, 'before' => array]. */
    #[Locked]
    public ?array $undo = null;

    /** The toast's text (a result or a calm problem). */
    public ?string $toast = null;

    /** Makes every toast a new element, so its timer starts over. */
    public int $toastSeq = 0;

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    /** @return Collection<int, Area> */
    #[Computed]
    public function areas(): Collection
    {
        return Area::forUser($this->user)->ordered()->get();
    }

    #[On('item-edit')]
    public function edit(int $id): void
    {
        try {
            $task = Task::forUser($this->user)->active()->findOrFail($id);
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $values = app(ItemEditorService::class)->values($task);
        $this->taskId = $task->id;
        $this->title = $values['title'];
        $this->list = $values['list'];
        $this->size = $values['size'];
        $this->hardDeadline = $values['hard_deadline'];
        $this->softDeadline = $values['soft_deadline'];
        $this->areaId = $values['area_id'];
        $this->firstStep = $values['first_step'];
        $this->notes = $values['notes'];
        $this->resetErrorBag();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->taskId = null;
        $this->resetErrorBag();
    }

    public function pick(string $list): void
    {
        if (in_array($list, Task::LISTS, true)) {
            $this->list = $list;
        }
    }

    /** A size chip: tapping the chosen one again takes the size away. */
    public function pickSize(string $size): void
    {
        if (Size::tryFrom($size) === null) {
            return;
        }

        $this->size = $this->size === $size ? null : $size;
    }

    public function save(ItemEditorService $items): void
    {
        if ($this->taskId === null) {
            return;
        }

        try {
            $result = $items->update($this->user, $this->taskId, [
                'title' => $this->title,
                'notes' => $this->notes,
                'list' => $this->list,
                'size' => $this->size,
                'hard_deadline' => $this->hardDeadline,
                'soft_deadline' => $this->softDeadline,
                'area_id' => $this->areaId,
                'first_step' => $this->firstStep,
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        } catch (ModelNotFoundException) {
            $this->close();
            $this->gone();

            return;
        } catch (Throwable $e) {
            report($e);
            $this->say(__('items.errors.failed'));

            return;
        }

        $this->close();
        $this->afterChange($result);
    }

    /** "Verschieben nach": the ⋯ menu, no sheet. */
    #[On('item-move')]
    public function move(int $id, string $list, ItemEditorService $items): void
    {
        try {
            $result = $items->move($this->user, $id, $list);
        } catch (ValidationException $e) {
            $this->say($e->validator->errors()->first('list') ?: __('items.errors.failed'));

            return;
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        } catch (Throwable $e) {
            report($e);
            $this->say(__('items.errors.failed'));

            return;
        }

        if ($result['moved']) {
            $this->afterChange($result);
        }
    }

    /** Delete from the ⋯ menu or from the sheet; undoable instead of confirmed (CONVENTIONS 7). */
    #[On('item-delete')]
    public function delete(?int $id = null): void
    {
        $id ??= $this->taskId;
        $items = app(ItemEditorService::class);

        if ($id === null) {
            return;
        }

        try {
            $task = $items->delete($this->user, $id);
        } catch (ItemLocked) {
            $this->say(__('items.errors.locked'));

            return;
        } catch (ModelNotFoundException) {
            $this->close();
            $this->gone();

            return;
        } catch (Throwable $e) {
            report($e);
            $this->say(__('items.errors.failed'));

            return;
        }

        $this->close();
        $this->undo = ['kind' => 'delete', 'id' => $task->id, 'before' => []];
        $this->say(__('items.toast.deleted', ['title' => Str::limit($task->title, 60)]), keepUndo: true);
        $this->dispatch('items-changed');
    }

    public function undoLast(ItemEditorService $items): void
    {
        $undo = $this->undo;
        $this->undo = null;

        if ($undo === null) {
            return;
        }

        try {
            if ($undo['kind'] === 'delete') {
                $task = $items->restore($this->user, (int) $undo['id']);
                $this->say(__('items.toast.delete_undone', ['title' => Str::limit($task->title, 60)]));
            } else {
                $items->undo($this->user, (int) $undo['id'], (array) $undo['before']);
                $this->say(__('items.toast.edit_undone'));
            }
        } catch (ModelNotFoundException) {
            $this->gone();

            return;
        }

        $this->dispatch('items-changed');
    }

    public function dismiss(): void
    {
        $this->toast = null;
        $this->undo = null;
    }

    /**
     * @param  array{task: Task, before: array<string, mixed>, moved: bool}  $result
     */
    private function afterChange(array $result): void
    {
        $task = $result['task'];
        $title = Str::limit($task->title, 60);

        $this->undo = ['kind' => 'edit', 'id' => $task->id, 'before' => $result['before']];
        $this->say($result['moved']
            ? __('items.toast.moved', ['title' => $title, 'list' => __('shell.lists.'.$task->list)])
            : __('items.toast.saved', ['title' => $title]), keepUndo: true);
        $this->dispatch('items-changed');
    }

    private function gone(): void
    {
        $this->say(__('items.errors.gone'));
        $this->dispatch('items-changed');
    }

    private function say(string $text, bool $keepUndo = false): void
    {
        if (! $keepUndo) {
            $this->undo = null;
        }

        $this->toast = $text;
        $this->toastSeq++;
    }

    public function render(): View
    {
        return view('livewire.shell.item-editor');
    }
}
