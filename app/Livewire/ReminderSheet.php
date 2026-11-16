<?php

namespace App\Livewire;

use App\Models\Reminder;
use App\Services\Reminders;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Alle Erinnerungen" — the sheet behind the chips above the Zeitstrahl: every
 * upcoming reminder to change, tick off or delete, and a form to add one.
 * Mounted once in the layout; opened with Livewire.dispatch('open-reminders',
 * { id }) (an id jumps straight into editing that reminder). All rules —
 * validation, the upcoming cap, what moving a reminder resets — are in
 * App\Services\Reminders; this component only translates form fields.
 */
class ReminderSheet extends Component
{
    public bool $open = false;

    /** Null while adding a new one; the id while editing. */
    public ?int $editingId = null;

    public string $formTitle = '';

    public string $formDate = '';

    public bool $formEvening = true;

    public bool $formOnDay = true;

    #[On('open-reminders')]
    public function openSheet(?int $id = null): void
    {
        $this->open = true;
        $this->resetForm();

        if ($id !== null) {
            $this->startEdit($id);
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    /** @return Collection<int, Reminder> */
    #[Computed]
    public function reminders(): Collection
    {
        return Reminders::upcoming(auth()->user());
    }

    /** Created from QuickCapture or ticked off in the chip row while this sheet is open. */
    #[On('captured')]
    #[On('reminders-changed')]
    public function refresh(): void
    {
        unset($this->reminders);
    }

    public function startEdit(int $id): void
    {
        $reminder = auth()->user()->reminders()->find($id);

        if ($reminder === null) {
            return;
        }

        $this->resetErrorBag();
        $this->editingId = $reminder->id;
        $this->formTitle = $reminder->title;
        $this->formDate = $reminder->remind_on->toDateString();
        $this->formEvening = $reminder->notify_evening_before;
        $this->formOnDay = $reminder->notify_on_day;
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        $data = [
            'title' => $this->formTitle,
            'remind_on' => $this->formDate,
            'notify_evening_before' => $this->formEvening,
            'notify_on_day' => $this->formOnDay,
        ];

        if ($this->editingId !== null) {
            $reminder = $user->reminders()->find($this->editingId);

            if ($reminder === null) {
                $this->resetForm();

                return;
            }

            Reminders::update($user, $reminder, $data);
        } else {
            Reminders::create($user, $data);
        }

        $this->afterWrite();
        $this->resetForm();
    }

    /** The round check beside a row: "Hab ich" / "doch noch nicht". */
    public function toggleAcknowledged(int $id): void
    {
        $reminder = auth()->user()->reminders()->find($id);

        if ($reminder !== null) {
            Reminders::acknowledge($reminder, ! $reminder->isAcknowledged());
            $this->afterWrite();
        }
    }

    public function delete(int $id): void
    {
        $reminder = auth()->user()->reminders()->find($id);

        if ($reminder === null) {
            return;
        }

        Reminders::delete($reminder);

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->afterWrite();
    }

    private function afterWrite(): void
    {
        unset($this->reminders);
        $this->dispatch('reminders-changed');
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->formTitle = '';
        $this->formDate = auth()->user()->localToday()->addDay()->toDateString();
        $this->formEvening = true;
        $this->formOnDay = true;
    }

    public function render()
    {
        return view('livewire.reminder-sheet');
    }
}
