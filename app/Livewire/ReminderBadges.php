<?php

namespace App\Livewire;

use App\Models\Reminder;
use App\Services\Reminders;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The strip of "Nicht vergessen" chips above the Zeitstrahl (included from
 * partials/schedule-strip.blade.php, so it appears on every board concept).
 * A child component of its own so ticking a reminder off re-renders just this
 * row — and every other copy of it on the page (the desktop and the mobile
 * layout each render one) through the `reminders-changed` event.
 *
 * Renders nothing but an empty root while no reminder is within reach: the
 * same zero-footprint rule as every other optional strip.
 */
class ReminderBadges extends Component
{
    /** @return Collection<int, Reminder> */
    #[Computed]
    public function reminders(): Collection
    {
        return Reminders::badges(auth()->user());
    }

    /** Open reminders further out than the badge window — see Reminders::laterCount(). */
    #[Computed]
    public function laterCount(): int
    {
        return Reminders::laterCount(auth()->user());
    }

    /** "Hab ich" — the pushes for it stop. Ownership is the owner relation; a foreign id is a no-op. */
    public function acknowledge(int $id): void
    {
        $reminder = auth()->user()->reminders()->find($id);

        if ($reminder === null) {
            return;
        }

        Reminders::acknowledge($reminder);

        unset($this->reminders, $this->laterCount);
        $this->dispatch('reminders-changed');
    }

    /** Created from QuickCapture, edited in the sheet, ticked off in another copy of this row — any of it makes this row stale. */
    #[On('captured')]
    #[On('reminders-changed')]
    public function refresh(): void
    {
        unset($this->reminders, $this->laterCount);
    }

    /**
     * The one line shown in place of a ticked-off chip. It says what "Hab ich"
     * just did, because the point of ticking is knowing the pushes are off.
     */
    public static function acknowledgedMessage(Reminder $reminder): string
    {
        $stillPending = $reminder->notify_on_day && $reminder->notified_on_at === null;
        $days = (int) round(auth()->user()->localToday()->diffInDays($reminder->remind_on->copy()->startOfDay(), false));

        return match (true) {
            $days === 1 && $stillPending => 'Morgen früh kommt nichts mehr.',
            $days === 0 => 'Eingepackt — nichts mehr offen.',
            default => 'Dann ist das erledigt.',
        };
    }

    public function render()
    {
        return view('livewire.reminder-badges');
    }
}
