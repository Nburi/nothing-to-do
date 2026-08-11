<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesAgendaSpaces;
use App\Models\AgendaSpace;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The landing page an invite link points at (`/agenda/join/{code}`).
 *
 * Joining is deliberately a button press, not a side effect of opening the URL:
 * a GET must not mutate, and a link pasted into a class chat gets fetched by
 * link previewers long before a human clicks it.
 */
#[Layout('layouts.app')]
class JoinAgendaSpace extends Component
{
    use ManagesAgendaSpaces;

    public string $code = '';

    public ?AgendaSpace $space = null;

    public bool $alreadyMember = false;

    public function mount(string $code): void
    {
        $this->code = $code;
        $this->space = AgendaSpace::findByInviteCode($code);

        $this->alreadyMember = $this->space !== null
            && $this->space->hasMember(auth()->user());
    }

    public function join()
    {
        if ($this->space === null) {
            return null;
        }

        $this->joinByCode($this->space->invite_code);

        session()->flash('agenda-joined', $this->space->name);

        return $this->redirectRoute('agenda', navigate: true);
    }

    public function render()
    {
        return view('livewire.join-agenda-space', [
            'memberCount' => $this->space?->members()->count() ?? 0,
        ]);
    }
}
